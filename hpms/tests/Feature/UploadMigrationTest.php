<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadMigrationTest extends TestCase
{
    public function test_dry_run_reports_files_without_writing(): void
    {
        Storage::fake('public');
        Storage::fake('r2-test');
        Storage::disk('public')->put('avatars/me.jpg', 'avatar');

        $this->artisan('uploads:migrate', [
            '--from' => 'public',
            '--to' => 'r2-test',
            '--dry-run' => true,
        ])->assertSuccessful();

        Storage::disk('r2-test')->assertMissing('avatars/me.jpg');
    }

    public function test_permanent_uploads_are_copied_and_other_files_are_ignored(): void
    {
        Storage::fake('public');
        Storage::fake('r2-test');
        Storage::disk('public')->put('avatars/me.jpg', 'avatar');
        Storage::disk('public')->put('branding/abc/logo.png', 'logo');
        Storage::disk('public')->put('private-notes.txt', 'not an upload');

        $this->artisan('uploads:migrate', ['--from' => 'public', '--to' => 'r2-test'])
            ->assertSuccessful();

        Storage::disk('r2-test')->assertExists('avatars/me.jpg');
        Storage::disk('r2-test')->assertExists('branding/abc/logo.png');
        Storage::disk('r2-test')->assertMissing('private-notes.txt');
        $this->assertSame('avatar', Storage::disk('r2-test')->get('avatars/me.jpg'));
        $this->assertSame('logo', Storage::disk('r2-test')->get('branding/abc/logo.png'));
        $this->assertSame('avatar', Storage::disk('public')->get('avatars/me.jpg'));
    }

    public function test_existing_objects_are_skipped_unless_overwrite_is_requested(): void
    {
        Storage::fake('public');
        Storage::fake('r2-test');
        Storage::disk('public')->put('avatars/me.jpg', 'new-avatar-bytes');
        Storage::disk('r2-test')->put('avatars/me.jpg', 'old');

        $this->artisan('uploads:migrate', ['--from' => 'public', '--to' => 'r2-test'])
            ->assertSuccessful();

        $this->assertSame('old', Storage::disk('r2-test')->get('avatars/me.jpg'));

        $this->artisan('uploads:migrate', ['--from' => 'public', '--to' => 'r2-test', '--overwrite' => true])
            ->assertSuccessful();

        $this->assertSame('new-avatar-bytes', Storage::disk('r2-test')->get('avatars/me.jpg'));
    }

    public function test_same_source_and_destination_is_rejected(): void
    {
        Storage::fake('public');

        $this->artisan('uploads:migrate', ['--from' => 'public', '--to' => 'public'])
            ->assertFailed();
    }
}
