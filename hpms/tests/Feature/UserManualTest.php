<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_users_can_open_the_bangla_user_manual_viewer(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/user-manual')
            ->assertOk()
            ->assertSee(__('navigation.user_manual'))
            ->assertSee('USER_MANUAL_BN.pdf')
            ->assertSee(route('manual.pdf'), false);
    }

    public function test_guest_is_redirected_away_from_the_manual(): void
    {
        $this->get('/user-manual')->assertRedirect('/login');
        $this->get('/user-manual/pdf')->assertRedirect('/login');
    }

    public function test_the_manual_pdf_is_served_inline(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->get('/user-manual/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }
}
