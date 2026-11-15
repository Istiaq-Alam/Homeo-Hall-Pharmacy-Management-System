<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChamberProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChamberIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function chamber(string $name): Chamber
    {
        $chamber = Chamber::query()->create(['name' => $name]);
        app(ChamberProvisioner::class)->ensureDefaults($chamber, $name.' Owner');

        return $chamber;
    }

    private function settingsFor(User $user): array
    {
        return [
            'chamber_name' => $user->chamber->name,
            'owner_name' => $user->name,
            'currency' => 'BDT',
            'currency_symbol' => '৳',
            'timezone' => 'Asia/Dhaka',
            'low_stock_default' => 5,
            'report_default_days' => 30,
        ];
    }

    public function test_creating_an_admin_account_provisions_a_separate_chamber_and_defaults(): void
    {
        $ownerChamber = $this->chamber('Original Chamber');
        $owner = User::factory()->create(['chamber_id' => $ownerChamber->id, 'name' => 'Original Owner', 'designation' => 'Owner']);

        $this->actingAs($owner)->post('/admin-accounts', [
            'name' => 'Dr. Rahman',
            'email' => 'rahman@example.com',
            'designation' => 'Chamber Administrator',
            'password' => 'correct-password-123',
            'password_confirmation' => 'correct-password-123',
        ])->assertRedirect()->assertSessionHas('success');

        $admin = User::query()->where('email', 'rahman@example.com')->firstOrFail();
        $this->assertNotSame($ownerChamber->id, $admin->chamber_id);
        $this->assertSame('Dr. Rahman', $admin->name);
        $this->assertSame('Chamber Administrator', $admin->designation);
        $this->assertSame('Dr. Rahman Chamber', $admin->chamber->name);

        $this->assertSame('Dr. Rahman Chamber', Setting::query()->forChamber($admin->chamber_id)->where('key', 'chamber_name')->value('value'));
        $this->assertSame(7, MedicineCategory::query()->forChamber($admin->chamber_id)->count());
        $this->assertSame(9, ExpenseCategory::query()->forChamber($admin->chamber_id)->count());
        $this->assertSame(7, MedicineCategory::query()->forChamber($ownerChamber->id)->count());

        $this->post('/logout');
        $this->post('/login', ['email' => 'rahman@example.com', 'password' => 'correct-password-123'])
            ->assertRedirect(route('dashboard'));
        $this->get('/settings')
            ->assertOk()
            ->assertSee('Dr. Rahman Chamber')
            ->assertSee('Chamber Administrator');
    }

    public function test_a_new_admin_can_rename_their_chamber_and_upload_their_own_logo(): void
    {
        Storage::fake('public');
        $ownerChamber = $this->chamber('Owner Chamber');
        $adminChamber = $this->chamber('Admin Chamber');
        User::factory()->create(['chamber_id' => $ownerChamber->id, 'name' => 'Owner']);
        $admin = User::factory()->create([
            'chamber_id' => $adminChamber->id,
            'name' => 'Dr. Rahman',
            'designation' => 'Chamber Administrator',
        ]);
        $logo = UploadedFile::fake()->createWithContent(
            'chamber-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );

        $this->actingAs($admin)->put('/settings', [
            ...$this->settingsFor($admin),
            'chamber_name' => 'Rahman Homeo Hall',
            'chamber_logo' => $logo,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Rahman Homeo Hall', $admin->chamber->name);
        $this->assertSame('Rahman Homeo Hall', Setting::query()->forChamber($adminChamber->id)->where('key', 'chamber_name')->value('value'));

        $logoPath = Setting::query()->forChamber($adminChamber->id)->where('key', 'chamber_logo')->value('value');
        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
        $this->assertNull(Setting::query()->forChamber($ownerChamber->id)->where('key', 'chamber_logo')->value('value'));

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Rahman Homeo Hall')
            ->assertSee($logoPath, false);
    }

    public function test_records_and_route_bindings_are_isolated_between_chambers(): void
    {
        $chamberA = $this->chamber('Chamber A');
        $chamberB = $this->chamber('Chamber B');
        $ownerA = User::factory()->create(['chamber_id' => $chamberA->id]);
        $ownerB = User::factory()->create(['chamber_id' => $chamberB->id]);
        $categoryA = MedicineCategory::query()->create(['chamber_id' => $chamberA->id, 'name' => 'Shared Name', 'is_active' => true]);
        $categoryB = MedicineCategory::query()->create(['chamber_id' => $chamberB->id, 'name' => 'Shared Name', 'is_active' => true]);
        $medicineA = Medicine::query()->create([
            'chamber_id' => $chamberA->id,
            'category_id' => $categoryA->id,
            'name' => 'Arnica Montana',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2,
            'selling_price' => 5,
            'minimum_stock' => 1,
        ]);
        $this->actingAs($ownerB)->get('/medicines')
            ->assertOk()
            ->assertDontSee('Arnica Montana');
        $this->actingAs($ownerB)->get('/medicines/'.$medicineA->id)->assertNotFound();
        $this->actingAs($ownerB)->get('/medicines/'.$medicineA->id.'/edit')->assertNotFound();
        $this->actingAs($ownerB)->put('/medicines/categories/'.$categoryA->id, ['name' => 'Changed'])->assertNotFound();

        $this->actingAs($ownerB)->post('/purchases', [
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicineA->id, 'quantity' => 1, 'unit_price' => 2]],
        ])->assertSessionHasErrors('items.0.medicine_id');

        $this->actingAs($ownerB)->post('/sales', [
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicineA->id, 'quantity' => 1, 'unit_price' => 5]],
        ])->assertSessionHasErrors('items.0.medicine_id');

        $this->actingAs($ownerA)->get('/medicines')
            ->assertOk()
            ->assertSee('Arnica Montana');
        $this->assertSame('Shared Name', $categoryA->fresh()->name);
        $this->assertSame($categoryB->id, $categoryB->fresh()->id);
    }

    public function test_backup_export_contains_only_the_signed_in_chamber(): void
    {
        $chamberA = $this->chamber('Chamber A');
        $chamberB = $this->chamber('Chamber B');
        $ownerA = User::factory()->create(['chamber_id' => $chamberA->id]);
        $categoryA = MedicineCategory::query()->create(['chamber_id' => $chamberA->id, 'name' => 'Category A', 'is_active' => true]);
        $categoryB = MedicineCategory::query()->create(['chamber_id' => $chamberB->id, 'name' => 'Category B', 'is_active' => true]);
        Medicine::query()->create([
            'chamber_id' => $chamberA->id,
            'category_id' => $categoryA->id,
            'name' => 'Chamber A Medicine',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2,
            'selling_price' => 5,
            'minimum_stock' => 1,
        ]);
        Medicine::query()->create([
            'chamber_id' => $chamberB->id,
            'category_id' => $categoryB->id,
            'name' => 'Chamber B Medicine',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2,
            'selling_price' => 5,
            'minimum_stock' => 1,
        ]);

        $response = $this->actingAs($ownerA)->get('/settings/backup')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $medicines = $zip->getFromName('medicines.csv');
        $settings = $zip->getFromName('settings.csv');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('Chamber A Medicine', $medicines);
        $this->assertStringNotContainsString('Chamber B Medicine', $medicines);
        $this->assertStringContainsString('Chamber A', $settings);
        $this->assertStringNotContainsString('Chamber B', $settings);
    }

    public function test_guest_queries_and_manifest_do_not_expose_chamber_data(): void
    {
        $chamber = $this->chamber('Private Chamber');
        User::factory()->create(['chamber_id' => $chamber->id, 'email' => 'private-owner@example.com']);
        MedicineCategory::query()->create(['chamber_id' => $chamber->id, 'name' => 'Private Category', 'is_active' => true]);

        $this->assertSame(0, MedicineCategory::query()->count());
        $this->assertNull(Setting::query()->where('key', 'chamber_name')->value('value'));

        $manifest = $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', 'Homeo-Hall · Chamber management');
        $this->assertStringContainsString('private', $manifest->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $manifest->headers->get('Cache-Control'));

        $this->post('/login', ['email' => 'private-owner@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', 'Private Chamber · Chamber management');
    }

    public function test_cross_chamber_categories_cannot_be_used_for_new_records(): void
    {
        $chamberA = $this->chamber('Chamber A');
        $chamberB = $this->chamber('Chamber B');
        $ownerA = User::factory()->create(['chamber_id' => $chamberA->id]);
        $ownerB = User::factory()->create(['chamber_id' => $chamberB->id]);
        $categoryA = MedicineCategory::query()->create(['chamber_id' => $chamberA->id, 'name' => 'Arnica', 'is_active' => true]);
        $expenseCategoryA = ExpenseCategory::query()->create(['chamber_id' => $chamberA->id, 'name' => 'Rent', 'is_active' => true]);

        $this->actingAs($ownerB)->post('/medicines', [
            'category_id' => $categoryA->id,
            'name' => 'Arnica Montana',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2,
            'selling_price' => 5,
            'minimum_stock' => 1,
        ])->assertSessionHasErrors('category_id');

        $this->actingAs($ownerB)->post('/expenses', [
            'expense_category_id' => $expenseCategoryA->id,
            'expense_date' => '2026-11-15',
            'description' => 'Cross-chamber expense',
            'amount' => 10,
        ])->assertSessionHasErrors('expense_category_id');

        $this->actingAs($ownerB)->post('/medicines/categories', ['name' => 'Arnica'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(1, MedicineCategory::query()->forChamber($chamberB->id)->where('name', 'Arnica')->count());
        $this->actingAs($ownerA)->post('/medicines/categories', ['name' => 'Arnica'])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, MedicineCategory::query()->forChamber($chamberA->id)->where('name', 'Arnica')->count());
    }
}
