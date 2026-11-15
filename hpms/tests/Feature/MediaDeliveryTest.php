<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_assets_are_public_but_avatars_are_not(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/abc/logo.png', 'brand-bytes');
        Storage::disk('public')->put('avatars/me.jpg', 'avatar-bytes');

        $this->get(MediaStorage::url('branding/abc/logo.png'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent('brand-bytes');

        $this->get(MediaStorage::url('avatars/me.jpg'))->assertNotFound();
    }

    public function test_a_signed_in_user_can_view_an_avatar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/me.jpg', 'avatar-bytes');

        $this->actingAs(User::factory()->create())
            ->get(MediaStorage::url('avatars/me.jpg'))
            ->assertOk()
            ->assertStreamedContent('avatar-bytes');
    }

    public function test_unknown_unsafe_and_missing_media_paths_are_not_found(): void
    {
        Storage::fake('public');

        $this->get('/media/other/secret.txt')->assertNotFound();
        $this->get('/media/branding/%2e%2e/avatars/me.jpg')->assertNotFound();
        $this->get('/media/branding/missing.png')->assertNotFound();
    }

    public function test_media_route_uses_the_configured_remote_uploads_disk(): void
    {
        config(['filesystems.uploads' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->put('branding/r2/logo.png', 'r2-bytes');

        $this->get(MediaStorage::url('branding/r2/logo.png'))
            ->assertOk()
            ->assertStreamedContent('r2-bytes');
    }

    public function test_remote_uploads_are_downloaded_for_local_image_processing(): void
    {
        config(['filesystems.uploads' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->put('branding/r2/logo.png', 'r2-bytes');

        $local = MediaStorage::localCopy('branding/r2/logo.png');

        $this->assertNotNull($local);
        $this->assertSame('r2-bytes', file_get_contents($local));

        MediaStorage::forgetLocalCopy($local);

        $this->assertFileDoesNotExist($local);
    }

    public function test_health_endpoints_stay_minimal(): void
    {
        $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok', 'database' => 'ok']);
        $this->get('/up')->assertOk();
    }
}
