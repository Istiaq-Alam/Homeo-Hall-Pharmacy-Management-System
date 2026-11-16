<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function bangla(string $key, array $replace = []): string
    {
        return __($key, $replace, 'bn');
    }

    public function test_switching_language_stores_the_locale_and_returns_to_the_same_page(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->from('/medicines?q=nux&status=low')
            ->post('/language', ['locale' => 'bn'])
            ->assertRedirect('/medicines?q=nux&status=low')
            ->assertSessionHas('locale', 'bn');

        $this->assertSame('bn', $owner->fresh()->locale);
    }

    public function test_a_signed_in_user_sees_their_saved_language_on_every_page(): void
    {
        $owner = User::factory()->create(['locale' => 'bn']);

        $this->actingAs($owner)->get('/medicines')->assertOk()->assertSee($this->bangla('medicines.title'));

        $this->actingAs($owner)->post('/language', ['locale' => 'en'])->assertRedirect();

        $this->actingAs($owner)->get('/medicines')->assertOk()->assertSee(__('medicines.title', [], 'en'));
    }

    public function test_guests_can_switch_language_and_the_choice_survives_the_session(): void
    {
        $this->from('/login')
            ->post('/language', ['locale' => 'bn'])
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'bn');

        $this->get('/login')->assertOk()->assertSee($this->bangla('auth.welcome_back'));
    }

    public function test_unsupported_locales_are_rejected_without_changing_the_preference(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);

        $this->actingAs($owner)
            ->from('/dashboard')
            ->post('/language', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('en', $owner->fresh()->locale);
        $this->actingAs($owner)->get('/dashboard')->assertSee(__('navigation.dashboard', [], 'en'));
    }

    public function test_the_switch_never_redirects_to_another_host(): void
    {
        $this->from('https://evil.example/phish')
            ->post('/language', ['locale' => 'bn'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_bangla_pages_render_translations_and_bengali_numerals(): void
    {
        $owner = User::factory()->create(['locale' => 'bn']);
        $category = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);
        Medicine::query()->create([
            'category_id' => $category->id,
            'name' => 'Nux Vomica',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 900,
            'selling_price' => 1250,
            'minimum_stock' => 2,
        ]);

        $response = $this->actingAs($owner)->get('/medicines')->assertOk();

        $response->assertSee($this->bangla('common.apply_filters'));
        $response->assertSee($this->bangla('defaults.medicine_categories.Dilution'));
        $response->assertSee('৳১,২৫০.০০');
        $response->assertSee('lang="bn"', false);
        $response->assertDontSee('৳1,250.00');
    }

    public function test_english_pages_keep_latin_numerals_and_taka_symbol(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);
        $category = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);
        Medicine::query()->create([
            'category_id' => $category->id,
            'name' => 'Nux Vomica',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 900,
            'selling_price' => 1250,
            'minimum_stock' => 2,
        ]);

        $this->actingAs($owner)->get('/medicines')
            ->assertOk()
            ->assertSee('৳1,250.00')
            ->assertSee('lang="en"', false);
    }

    public function test_business_operations_work_in_bangla_without_localizing_stored_values(): void
    {
        $owner = User::factory()->create(['locale' => 'bn']);
        $category = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);
        ExpenseCategory::query()->create(['name' => 'Electricity', 'is_active' => true]);

        $this->actingAs($owner)->post('/medicines', [
            'category_id' => $category->id,
            'name' => 'Arnica Montana',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 4,
            'selling_price' => 10,
            'minimum_stock' => 1,
        ])->assertRedirect()->assertSessionHas('success', $this->bangla('medicines.flash.added'));

        $medicine = Medicine::query()->where('name', 'Arnica Montana')->firstOrFail();

        $this->actingAs($owner)->post('/purchases', [
            'purchase_date' => '2026-11-10',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 12, 'unit_price' => 4]],
        ])->assertRedirect()->assertSessionHas('success', $this->bangla('purchases.flash.recorded'));

        $this->actingAs($owner)->post('/sales', [
            'sale_date' => '2026-11-11',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2, 'unit_price' => 10]],
        ])->assertRedirect()->assertSessionHas('success', $this->bangla('sales.flash.recorded'));

        // Stored values stay in Latin numerals and the medicine keeps its name.
        $this->assertSame('Arnica Montana', $medicine->fresh()->name);
        $this->assertSame(10, (int) $medicine->fresh()->stock_quantity);
        $this->assertSame(1, DB::table('sales')->count());

        $this->actingAs($owner)->get('/medicines')->assertOk()
            ->assertSee('Arnica Montana')
            ->assertSee('১০');

        $this->actingAs($owner)->get('/reports')->assertOk()
            ->assertSee($this->bangla('reports.title'))
            ->assertSee($this->bangla('reports.net_operating_profit'));
    }

    public function test_validation_errors_and_expense_flow_are_localized(): void
    {
        $owner = User::factory()->create(['locale' => 'bn']);

        $this->actingAs($owner)
            ->post('/expenses', [])
            ->assertSessionHasErrors(['expense_category_id', 'expense_date', 'description', 'amount']);

        $this->actingAs($owner)->get('/expenses/create')
            ->assertOk()
            ->assertSee($this->bangla('expenses.title'))
            ->assertSee($this->bangla('expenses.amount'));
    }

    public function test_unpaid_sales_and_the_user_manual_are_available_in_bangla(): void
    {
        $owner = User::factory()->create(['locale' => 'bn']);

        $this->actingAs($owner)->get('/sales/create')->assertOk()
            ->assertSee($this->bangla('sales.keep_unpaid'))
            ->assertSee($this->bangla('sales.keep_unpaid_hint'));

        $this->actingAs($owner)->get('/user-manual')->assertOk()
            ->assertSee($this->bangla('navigation.user_manual'))
            ->assertSee($this->bangla('manual.title'));
    }

    public function test_settings_and_reports_screens_are_translated(): void
    {
        $chamber = Chamber::query()->create(['name' => 'Green Chamber']);
        $owner = User::factory()->create(['locale' => 'bn', 'chamber_id' => $chamber->id]);

        $this->actingAs($owner)->get('/settings')->assertOk()
            ->assertSee($this->bangla('settings.general'))
            ->assertSee($this->bangla('settings.change_password'));

        $this->actingAs($owner)->get('/themes')->assertOk()
            ->assertSee($this->bangla('themes.hover_hint'))
            ->assertSee($this->bangla('themes.items.apple.tone'));

        $this->actingAs($owner)->get('/reports/monthly')->assertOk()
            ->assertSee($this->bangla('reports.six_month_comparison'));
    }

    public function test_pagination_controls_are_translated_and_use_bengali_numerals(): void
    {
        $owner = User::factory()->create(['locale' => 'bn']);
        $category = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);

        foreach (range(1, 30) as $index) {
            Medicine::query()->create([
                'category_id' => $category->id,
                'name' => 'Medicine '.$index,
                'potency' => '30C',
                'form' => 'Liquid',
                'unit' => 'Bottle',
                'purchase_price' => 5,
                'selling_price' => 10,
                'minimum_stock' => 1,
            ]);
        }

        $response = $this->actingAs($owner)->get('/medicines')->assertOk();

        $response->assertSee($this->bangla('pagination.navigation'), false);
        $response->assertSee($this->bangla('pagination.next'), false);
        $response->assertSee('<span aria-current="page"><span>১</span></span>', false);
        $response->assertDontSee('Previous', false);
        $response->assertDontSee('Showing 1 to', false);
    }

    public function test_the_manifest_reports_the_active_language(): void
    {
        $owner = User::factory()->create(['locale' => 'bn']);

        $this->actingAs($owner)->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('lang', 'bn');
    }
}
