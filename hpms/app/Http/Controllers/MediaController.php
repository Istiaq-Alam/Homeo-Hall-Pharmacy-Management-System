<?php

namespace App\Http\Controllers;

use App\Support\MediaStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Serve an uploaded logo or avatar from the configured uploads disk.
     *
     * Branding files are safe to show on the login screen and PWA manifest;
     * avatars require a signed-in user. Nothing is exposed from a public
     * bucket, so a private R2 bucket keeps working.
     */
    public function show(string $path): StreamedResponse
    {
        $path = ltrim($path, '/');

        abort_unless(
            preg_match('#^(avatars|branding)/[A-Za-z0-9][A-Za-z0-9._/-]*$#', $path) === 1
                && ! str_contains($path, '..'),
            404,
        );

        $isAvatar = str_starts_with($path, MediaStorage::AVATAR_DIRECTORY.'/');

        if ($isAvatar) {
            abort_unless(auth()->check(), 404);
        }

        $disk = MediaStorage::disk();

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => $isAvatar ? 'private, max-age=3600' : 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
