<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\User;
use App\Services\ChamberProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_deleting_an_owner_with_staff_is_blocked_until_the_staff_are_removed(): void
    {
        $admin = User::factory()->admin()->create();
        $chamber = $this->chamber('Owner Chamber');
        $owner = User::factory()->owner()->create(['chamber_id' => $chamber->id]);
        $staff = User::factory()->staff()->create(['chamber_id' => $chamber->id]);

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $owner))
            ->assertRedirect()
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('chambers', ['id' => $chamber->id]);

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $staff))
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $owner))
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('chambers', ['id' => $chamber->id]);
    }
}
