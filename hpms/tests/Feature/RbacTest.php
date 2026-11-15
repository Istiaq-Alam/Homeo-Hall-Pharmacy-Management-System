<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\ChamberProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function chamber(string $name): Chamber
    {
        $chamber = Chamber::query()->create(['name' => $name]);
        app(ChamberProvisioner::class)->ensureDefaults($chamber, $name.' Owner');

        return $chamber;
    }

    public function test_owner_can_create_and_remove_staff_in_their_chamber_only(): void
    {
        $chamber = $this->chamber('Owner Chamber');
        $owner = User::factory()->owner()->create(['chamber_id' => $chamber->id]);
        $otherChamber = $this->chamber('Other Chamber');
        $otherStaff = User::factory()->staff()->create(['chamber_id' => $otherChamber->id]);

        $this->actingAs($owner)->post(route('settings.staff.store'), [
            'name' => 'Counter Staff',
            'email' => 'staff@example.com',
            'designation' => 'Counter Staff',
            'password' => 'correct-password-123',
            'password_confirmation' => 'correct-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $staff = User::query()->where('email', 'staff@example.com')->firstOrFail();
        $this->assertSame(UserRole::Staff, $staff->role);
        $this->assertSame($chamber->id, $staff->chamber_id);
        $this->assertTrue(Hash::check('correct-password-123', $staff->password));

        $this->actingAs($owner)->get('/settings')
            ->assertOk()
            ->assertSee('Staff accounts')
            ->assertSee('staff@example.com');

        $this->actingAs($owner)->delete(route('settings.staff.destroy', $otherStaff))
            ->assertForbidden();

        $this->actingAs($owner)->delete(route('settings.staff.destroy', $staff))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('users', ['id' => $otherStaff->id]);
    }

    public function test_staff_can_use_chamber_operations_but_cannot_manage_the_chamber_or_accounts(): void
    {
        $chamber = $this->chamber('Staff Chamber');
        User::factory()->owner()->create(['chamber_id' => $chamber->id]);
        $staff = User::factory()->staff()->create(['chamber_id' => $chamber->id]);

        $this->actingAs($staff)->get('/medicines')->assertOk();
        $this->actingAs($staff)->get('/settings')
            ->assertOk()
            ->assertDontSee('General settings')
            ->assertDontSee('Data backup')
            ->assertDontSee('Staff accounts');

        $this->actingAs($staff)->put('/settings', [])->assertForbidden();
        $this->actingAs($staff)->get('/settings/backup')->assertForbidden();
        $this->actingAs($staff)->post('/settings/staff', [])->assertForbidden();
        $this->actingAs($staff)->get('/admin')->assertForbidden();

        $this->actingAs($staff)->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $staff->fresh()->password));
    }

    public function test_owner_cannot_access_the_admin_console_or_manage_other_accounts(): void
    {
        $chamber = $this->chamber('Owner Chamber');
        $owner = User::factory()->owner()->create(['chamber_id' => $chamber->id]);
        $staff = User::factory()->staff()->create(['chamber_id' => $chamber->id]);

        $this->actingAs($owner)->get('/admin')->assertForbidden();
        $this->actingAs($owner)->post('/admin/accounts', [])->assertForbidden();
        $this->actingAs($owner)->delete(route('admin.accounts.destroy', $staff))->assertForbidden();
    }

    public function test_admin_must_choose_a_chamber_and_can_switch_between_chambers(): void
    {
        $chamberA = $this->chamber('Chamber A');
        $chamberB = $this->chamber('Chamber B');
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
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.index'));
        $this->actingAs($admin)->get('/medicines')->assertRedirect(route('admin.index'));

        $this->actingAs($admin)->post(route('admin.chambers.activate', $chamberA))
            ->assertRedirect(route('dashboard'));
        $this->assertSame($chamberA->id, session('active_chamber_id'));
        $this->actingAs($admin)->get('/medicines')
            ->assertOk()
            ->assertSee('Chamber A Medicine')
            ->assertDontSee('Chamber B Medicine');

        $this->actingAs($admin)->post(route('admin.chambers.activate', $chamberB))
            ->assertRedirect(route('dashboard'));
        $this->assertSame($chamberB->id, session('active_chamber_id'));
        $this->actingAs($admin)->get('/medicines')
            ->assertOk()
            ->assertSee('Chamber B Medicine')
            ->assertDontSee('Chamber A Medicine');

        $sidebar = $this->actingAs($admin)->get('/medicines')
            ->assertOk()
            ->assertSee('class="active-chamber"', false)
            ->assertSee('Switch', false);
        $switcherPosition = strpos($sidebar->getContent(), 'class="active-chamber"');
        $profilePosition = strpos($sidebar->getContent(), 'class="owner-pill"');
        $installButtonPosition = strpos($sidebar->getContent(), 'id="install-app"');
        $this->assertNotFalse($switcherPosition);
        $this->assertNotFalse($profilePosition);
        $this->assertNotFalse($installButtonPosition);
        $this->assertLessThan($profilePosition, $switcherPosition);
        $this->assertLessThan($installButtonPosition, $switcherPosition);
    }

    public function test_admin_can_create_system_admins_and_staff_for_any_chamber(): void
    {
        $admin = User::factory()->admin()->create();
        $chamber = $this->chamber('Target Chamber');

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'role' => UserRole::Admin->value,
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'designation' => 'System Administrator',
            'password' => 'correct-password-123',
            'password_confirmation' => 'correct-password-123',
        ])->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $secondAdmin = User::query()->where('email', 'second-admin@example.com')->firstOrFail();
        $this->assertSame(UserRole::Admin, $secondAdmin->role);
        $this->assertNull($secondAdmin->chamber_id);

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'role' => UserRole::Staff->value,
            'name' => 'Target Staff',
            'email' => 'target-staff@example.com',
            'designation' => 'Counter Staff',
            'chamber_id' => $chamber->id,
            'password' => 'correct-password-123',
            'password_confirmation' => 'correct-password-123',
        ])->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $staff = User::query()->where('email', 'target-staff@example.com')->firstOrFail();
        $this->assertSame(UserRole::Staff, $staff->role);
        $this->assertSame($chamber->id, $staff->chamber_id);
    }

    public function test_admin_dashboard_shows_month_to_date_totals_for_every_chamber(): void
    {
        $chamberA = $this->chamber('North Chamber');
        $chamberB = $this->chamber('South Chamber');
        $categoryA = MedicineCategory::query()->create(['chamber_id' => $chamberA->id, 'name' => 'North Remedies', 'is_active' => true]);
        $categoryB = MedicineCategory::query()->create(['chamber_id' => $chamberB->id, 'name' => 'South Remedies', 'is_active' => true]);
        $medicineA = Medicine::query()->create([
            'chamber_id' => $chamberA->id,
            'category_id' => $categoryA->id,
            'name' => 'North Arnica',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 30,
            'selling_price' => 100,
            'minimum_stock' => 0,
        ]);
        $medicineB = Medicine::query()->create([
            'chamber_id' => $chamberB->id,
            'category_id' => $categoryB->id,
            'name' => 'South Arnica',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 250,
            'selling_price' => 350,
            'minimum_stock' => 0,
        ]);
        $today = CarbonImmutable::today(config('app.timezone'))->toDateString();

        $saleA = Sale::query()->create([
            'chamber_id' => $chamberA->id,
            'sale_number' => 'NORTH-001',
            'sale_date' => $today,
            'total_amount' => 300,
            'status' => 'completed',
        ]);
        SaleItem::query()->create([
            'chamber_id' => $chamberA->id,
            'sale_id' => $saleA->id,
            'medicine_id' => $medicineA->id,
            'quantity' => 3,
            'unit_selling_price' => 100,
            'unit_cost_price' => 30,
            'line_total' => 300,
            'line_cost_total' => 90,
        ]);
        $saleB = Sale::query()->create([
            'chamber_id' => $chamberB->id,
            'sale_number' => 'SOUTH-001',
            'sale_date' => $today,
            'total_amount' => 700,
            'status' => 'completed',
        ]);
        SaleItem::query()->create([
            'chamber_id' => $chamberB->id,
            'sale_id' => $saleB->id,
            'medicine_id' => $medicineB->id,
            'quantity' => 2,
            'unit_selling_price' => 350,
            'unit_cost_price' => 250,
            'line_total' => 700,
            'line_cost_total' => 500,
        ]);
        Sale::query()->create([
            'chamber_id' => $chamberA->id,
            'sale_number' => 'NORTH-CANCELLED',
            'sale_date' => $today,
            'total_amount' => 900,
            'status' => 'cancelled',
        ]);
        Purchase::query()->create([
            'chamber_id' => $chamberA->id,
            'purchase_number' => 'NORTH-PURCHASE',
            'purchase_date' => $today,
            'total_amount' => 50,
            'status' => 'completed',
        ]);
        Purchase::query()->create([
            'chamber_id' => $chamberB->id,
            'purchase_number' => 'SOUTH-PURCHASE',
            'purchase_date' => $today,
            'total_amount' => 100,
            'status' => 'completed',
        ]);
        $expenseCategoryA = ExpenseCategory::query()->create(['chamber_id' => $chamberA->id, 'name' => 'North Utilities', 'is_active' => true]);
        $expenseCategoryB = ExpenseCategory::query()->create(['chamber_id' => $chamberB->id, 'name' => 'South Utilities', 'is_active' => true]);
        Expense::query()->create([
            'chamber_id' => $chamberA->id,
            'expense_category_id' => $expenseCategoryA->id,
            'expense_date' => $today,
            'description' => 'North utility bill',
            'amount' => 20,
            'status' => 'completed',
        ]);
        Expense::query()->create([
            'chamber_id' => $chamberB->id,
            'expense_category_id' => $expenseCategoryB->id,
            'expense_date' => $today,
            'description' => 'South utility bill',
            'amount' => 100,
            'status' => 'completed',
        ]);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $response->assertSee('Chamber performance')
            ->assertSee('North Chamber')
            ->assertSee('South Chamber')
            ->assertSee('৳1,000.00')
            ->assertSee('৳290.00')
            ->assertSee('৳300.00')
            ->assertSee('৳700.00')
            ->assertDontSee('NORTH-CANCELLED');
    }

    public function test_admin_can_update_own_email_password_and_profile_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $image = UploadedFile::fake()->createWithContent(
            'admin-profile.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => 'Updated Administrator',
            'email' => 'updated-admin@example.com',
            'designation' => 'System Administrator',
            'current_password' => 'password',
            'password' => 'new-admin-password-123',
            'password_confirmation' => 'new-admin-password-123',
            'profile_image' => $image,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Updated Administrator', $admin->name);
        $this->assertSame('updated-admin@example.com', $admin->email);
        $this->assertSame('System Administrator', $admin->designation);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('new-admin-password-123', $admin->password));
        $this->assertNotNull($admin->avatar_path);
        Storage::disk('public')->assertExists($admin->avatar_path);
    }

    public function test_admin_must_confirm_current_password_to_change_email_or_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => $admin->name,
            'email' => 'changed-admin@example.com',
            'password' => 'new-admin-password-123',
            'password_confirmation' => 'new-admin-password-123',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($admin->email, $admin->fresh()->email);
    }

    public function test_deleting_a_chamber_owner_removes_the_chamber_and_all_of_its_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $chamber = $this->chamber('Owner Chamber');
        $otherChamber = $this->chamber('Other Chamber');
        $otherOwner = User::factory()->owner()->create(['chamber_id' => $otherChamber->id]);
        $owner = User::factory()->owner()->create(['chamber_id' => $chamber->id]);
        $staff = User::factory()->staff()->create(['chamber_id' => $chamber->id]);
        $category = MedicineCategory::query()->create([
            'chamber_id' => $chamber->id,
            'name' => 'Owner Chamber Category',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $owner))
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseMissing('chambers', ['id' => $chamber->id]);
        $this->assertDatabaseMissing('medicine_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('settings', ['chamber_id' => $chamber->id]);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $otherOwner->id]);
        $this->assertDatabaseHas('chambers', ['id' => $otherChamber->id]);
    }

    public function test_every_chamber_created_through_the_admin_console_has_its_own_owner(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'role' => UserRole::Owner->value,
            'name' => 'New Owner',
            'email' => 'new-owner@example.com',
            'designation' => 'Chamber Owner',
            'chamber_name' => 'New Owner Chamber',
            'password' => 'correct-password-123',
            'password_confirmation' => 'correct-password-123',
        ])->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $owner = User::query()->where('email', 'new-owner@example.com')->firstOrFail();
        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertNotNull($owner->chamber_id);
        $this->assertSame('New Owner Chamber', $owner->chamber->name);

        $ownerlessChambers = Chamber::query()
            ->whereDoesntHave('users', fn ($query) => $query->where('role', UserRole::Owner))
            ->count();
        $this->assertSame(0, $ownerlessChambers);
    }
}
