<?php

namespace Tests\Feature;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_ins_and_sign_outs_are_written_to_the_security_log(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'audit-admin@example.com']);

        $this->post(route('login'), ['email' => 'audit-admin@example.com', 'password' => 'not-the-password'])
            ->assertSessionHasErrors('email');

        $failed = SecurityLog::query()->where('event', SecurityLog::SIGN_IN_FAILED)->sole();
        $this->assertNull($failed->user_id);
        $this->assertStringContainsString('audit-admin@example.com', $failed->description);
        $this->assertNotNull($failed->ip_address);

        $this->post(route('login'), ['email' => 'audit-admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('security_logs', [
            'event' => SecurityLog::SIGN_IN,
            'user_id' => $admin->id,
            'actor_email' => 'audit-admin@example.com',
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('security_logs', ['event' => SecurityLog::SIGN_OUT, 'user_id' => $admin->id]);
    }

    public function test_the_administration_page_shows_recent_security_events(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'security-admin@example.com']);
        SecurityLog::record(SecurityLog::SIGN_IN, 'Signed in successfully.', $admin);

        $this->actingAs($admin)->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Security log')
            ->assertSee('Signed in')
            ->assertSee('security-admin@example.com');
    }

    public function test_admin_can_delete_all_security_log_entries(): void
    {
        $admin = User::factory()->admin()->create();
        SecurityLog::record(SecurityLog::SIGN_IN, 'Signed in successfully.', $admin);
        SecurityLog::record(SecurityLog::SIGN_OUT, 'Signed out.', $admin);

        $this->actingAs($admin)->delete(route('admin.security-log.clear'))
            ->assertRedirect(route('admin.index').'#security-log')
            ->assertSessionHas('success');

        $this->assertDatabaseCount('security_logs', 0);
        $this->actingAs($admin)->get(route('admin.index'))
            ->assertOk()
            ->assertSee(__('admin.no_events'));
    }

    public function test_only_admins_can_delete_the_security_log(): void
    {
        $owner = User::factory()->owner()->create();
        SecurityLog::record(SecurityLog::SIGN_IN, 'Signed in successfully.', $owner);

        $this->actingAs($owner)->delete(route('admin.security-log.clear'))->assertForbidden();

        $this->assertDatabaseCount('security_logs', 1);
    }

    public function test_account_creation_and_deletion_are_logged(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'owner-admin@example.com']);

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'role' => 'owner',
            'name' => 'New Owner',
            'email' => 'new-owner@example.com',
            'designation' => 'Chamber Owner',
            'password' => 'strong-password-123',
            'password_confirmation' => 'strong-password-123',
            'chamber_name' => 'Audit Chamber',
        ])->assertRedirect(route('admin.index'));

        $this->assertDatabaseHas('security_logs', [
            'event' => SecurityLog::ACCOUNT_CREATED,
            'actor_email' => 'owner-admin@example.com',
        ]);

        $owner = User::query()->where('email', 'new-owner@example.com')->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $owner))
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseHas('security_logs', [
            'event' => SecurityLog::ACCOUNT_DELETED,
            'actor_email' => 'owner-admin@example.com',
        ]);
    }
}
