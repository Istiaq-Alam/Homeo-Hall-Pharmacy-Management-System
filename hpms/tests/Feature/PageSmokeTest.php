<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\TransactionService;
use App\Support\BrandAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_primary_owner_page_renders(): void
    {
        $owner = User::factory()->create();
        $medicineCategory = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);
        $expenseCategory = ExpenseCategory::query()->create(['name' => 'Electricity', 'is_active' => true]);
        $medicine = Medicine::query()->create([
            'category_id' => $medicineCategory->id,
            'name' => 'Nux Vomica',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2,
            'selling_price' => 5,
            'minimum_stock' => 2,
        ]);
        $transactions = app(TransactionService::class);
        $purchase = $transactions->recordPurchase([
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 5, 'unit_price' => 2]],
        ], $owner->id);
        $sale = $transactions->recordSale([
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 5]],
        ], $owner->id);
        $expense = $transactions->recordExpense([
            'expense_category_id' => $expenseCategory->id,
            'expense_date' => '2026-11-15',
            'description' => 'Test bill',
            'amount' => 5,
        ], $owner->id);

        $paths = [
            '/dashboard',
            '/medicines',
            '/medicines/create',
            '/medicines/categories',
            '/medicines/adjustments/create',
            '/medicines/'.$medicine->id,
            '/medicines/'.$medicine->id.'/edit',
            '/purchases',
            '/purchases/create',
            '/purchases/'.$purchase->id,
            '/sales',
            '/sales/create',
            '/sales/'.$sale->id,
            '/expenses',
            '/expenses/create',
            '/expenses/categories',
            '/expenses/'.$expense->id,
            '/expenses/'.$expense->id.'/edit',
            '/reports',
            '/reports/daily',
            '/reports/monthly',
            '/reports/yearly',
            '/reports/inventory',
            '/reports/medicine-sales',
            '/reports/expenses',
            '/reports/low-stock',
            '/reports/stock-movements',
            '/settings',
        ];

        foreach ($paths as $path) {
            $this->actingAs($owner)->get($path)->assertOk();
        }

        $this->get('/health')->assertOk()->assertJson(['status' => 'ok', 'database' => 'ok']);
    }

    public function test_owner_can_upload_and_remove_a_chamber_logo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $logo = UploadedFile::fake()->createWithContent(
            'chamber-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
        $settings = [
            'chamber_name' => 'Homeo-Hall',
            'owner_name' => 'Administrator',
            'currency' => 'BDT',
            'currency_symbol' => '৳',
            'timezone' => 'Asia/Dhaka',
            'low_stock_default' => 5,
            'report_default_days' => 30,
        ];

        $this->actingAs($owner)->put('/settings', [...$settings, 'chamber_logo' => $logo])->assertRedirect();

        $path = Setting::query()->where('key', 'chamber_logo')->value('value');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('brand-logo', false)
            ->assertSee($path, false);

        $assets = json_decode((string) Setting::query()->where('key', 'chamber_brand_assets')->value('value'), true);

        if (BrandAssets::supported()) {
            $this->assertIsArray($assets);
            $this->assertCount(16, $assets['splash']);

            foreach (['mark', 'favicon32', 'favicon48', 'appleTouch', 'icon192', 'icon512', 'maskable192', 'maskable512'] as $key) {
                Storage::disk('public')->assertExists($assets[$key]);
            }

            foreach ($assets['splash'] as $splash) {
                Storage::disk('public')->assertExists($splash);
            }

            $this->actingAs($owner)->get('/dashboard')
                ->assertOk()
                ->assertSee($assets['favicon32'], false)
                ->assertSee($assets['splash']['1290x2796'], false);
        }

        $this->actingAs($owner)->put('/settings', [...$settings, 'remove_logo' => 1])->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'chamber_logo']);
        $this->assertDatabaseMissing('settings', ['key' => 'chamber_brand_assets']);
        Storage::disk('public')->assertMissing($path);
        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('brand-logo', false);
    }

    public function test_manifest_serves_the_chamber_generated_icons(): void
    {
        Storage::fake('public');
        $chamber = Chamber::query()->create(['name' => 'Green Chamber']);
        $owner = User::factory()->create(['chamber_id' => $chamber->id]);
        $logo = UploadedFile::fake()->createWithContent(
            'chamber-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
        $settings = [
            'chamber_name' => 'Green Chamber',
            'owner_name' => 'Administrator',
            'currency' => 'BDT',
            'currency_symbol' => '৳',
            'timezone' => 'Asia/Dhaka',
            'low_stock_default' => 5,
            'report_default_days' => 30,
        ];

        $this->actingAs($owner)->put('/settings', [...$settings, 'chamber_logo' => $logo])->assertRedirect();

        $icons = $this->actingAs($owner)->get('/manifest.webmanifest')->assertOk()->json('icons');
        $this->assertCount(4, $icons);

        if (! BrandAssets::supported()) {
            $this->assertSame('/icons/icon-192.png', $icons[0]['src']);

            return;
        }

        $this->assertStringContainsString('branding/', $icons[0]['src']);
        $this->assertSame('192x192', $icons[0]['sizes']);
        $this->assertSame('512x512', $icons[1]['sizes']);
        $this->assertSame('maskable', $icons[2]['purpose']);
        $this->assertStringContainsString('icon-maskable-512.png', $icons[3]['src']);
    }

    public function test_owner_can_upload_and_remove_a_profile_picture(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['name' => 'Administrator']);
        $picture = UploadedFile::fake()->createWithContent(
            'administrator.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
        $settings = [
            'chamber_name' => 'Homeo-Hall',
            'owner_name' => 'Administrator',
            'currency' => 'BDT',
            'currency_symbol' => '৳',
            'timezone' => 'Asia/Dhaka',
            'low_stock_default' => 5,
            'report_default_days' => 30,
        ];

        $this->actingAs($owner)->put('/settings', [...$settings, 'owner_avatar' => $picture])->assertRedirect();

        $owner->refresh();
        $path = $owner->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('avatar-photo', false)
            ->assertSee($path, false);

        $this->actingAs($owner)->put('/settings', [...$settings, 'remove_avatar' => 1])->assertRedirect();

        $owner->refresh();
        $this->assertNull($owner->avatar_path);
        Storage::disk('public')->assertMissing($path);
        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('avatar-photo', false);
    }

    public function test_pwa_manifest_and_mobile_shell_are_installable(): void
    {
        $owner = User::factory()->create();

        $manifest = $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('start_url', '/dashboard');

        $icons = $manifest->json('icons');
        $this->assertCount(4, $icons);
        foreach ($icons as $icon) {
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file);
            [$width, $height] = getimagesize($file);
            $this->assertSame($icon['sizes'], $width.'x'.$height);
        }

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('id="menu-toggle"', false)
            ->assertDontSee('id="sidebar-close"', false)
            ->assertSee('id="install-app"', false)
            ->assertSee('viewport-fit=cover', false);
    }
}
