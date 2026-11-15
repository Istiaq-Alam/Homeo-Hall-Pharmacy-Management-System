<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the disk that holds permanent uploads (chamber logos and avatars).
 *
 * Local development keeps using the public disk. Production points
 * UPLOADS_DISK at the private R2 bucket, so URLs must go through the
 * authenticated media route instead of public/storage.
 */
class MediaStorage
{
    public const AVATAR_DIRECTORY = 'avatars';

    public const BRAND_DIRECTORY = 'branding';

    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.uploads', 'public'));
    }

    public static function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return route('media.show', ['path' => ltrim($path, '/')]);
    }

    /**
     * Download a remote upload to a local temporary file.
     *
     * Image processing (BrandAssets) needs a filesystem path, which remote
     * disks do not expose. Local uploads are returned in place.
     */
    public static function localCopy(string $path): ?string
    {
        $disk = self::disk();
        $name = config('filesystems.uploads', 'public');

        if (config("filesystems.disks.{$name}.driver") === 'local') {
            return is_file($disk->path($path)) ? $disk->path($path) : null;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'hpms-media-');

        if ($temporary === false) {
            return null;
        }

        $stream = $disk->readStream($path);

        if ($stream === null) {
            @unlink($temporary);

            return null;
        }

        $target = fopen($temporary, 'wb');

        if ($target === false) {
            fclose($stream);
            @unlink($temporary);

            return null;
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $temporary;
    }

    public static function forgetLocalCopy(?string $path): void
    {
        if ($path === null) {
            return;
        }

        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hpms-media-';

        if (str_starts_with($path, $prefix) && is_file($path)) {
            @unlink($path);
        }
    }
}
