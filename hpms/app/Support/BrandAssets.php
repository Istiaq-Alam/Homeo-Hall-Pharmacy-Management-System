<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Derives the install identity (favicons, PWA icons and the Apple touch icon)
 * from a chamber's uploaded logo, so each chamber can white-label the app
 * without shipping artwork per tenant.
 *
 * Launch screens are not generated: the head partial links the bundled artwork
 * in public/icons/splash, which already carries the Homeo-Hall name.
 *
 * GD is used when the extension is loaded; otherwise the ImageMagick binary is
 * invoked. Without either backend the caller keeps the bundled Homeo-Hall
 * assets, so a logo upload never fails because of image processing.
 */
class BrandAssets
{
    /**
     * Bundled launch screens in public/icons/splash as CSS width, CSS height
     * and device pixel ratio.
     *
     * @var list<array{0: int, 1: int, 2: int}>
     */
    public const SPLASH = [
        [430, 932, 3],
        [393, 852, 3],
        [428, 926, 3],
        [390, 844, 3],
        [375, 812, 3],
        [414, 896, 3],
        [414, 896, 2],
        [375, 667, 2],
        [1024, 1366, 2],
        [834, 1194, 2],
        [820, 1180, 2],
        [768, 1024, 2],
        [1366, 1024, 2],
        [1194, 834, 2],
        [1180, 820, 2],
        [1024, 768, 2],
    ];

    /**
     * Square renders: key, file name, pixel size and the share of the canvas
     * the artwork may occupy (maskable icons stay inside the 80% safe zone).
     *
     * @var array<string, array{file: string, size: int, ratio: float}>
     */
    private const SQUARE = [
        'mark' => ['file' => 'mark-96.png', 'size' => 96, 'ratio' => 0.84],
        'favicon32' => ['file' => 'favicon-32.png', 'size' => 32, 'ratio' => 0.92],
        'favicon48' => ['file' => 'favicon-48.png', 'size' => 48, 'ratio' => 0.92],
        'appleTouch' => ['file' => 'apple-touch-icon.png', 'size' => 180, 'ratio' => 0.86],
        'icon192' => ['file' => 'icon-192.png', 'size' => 192, 'ratio' => 0.92],
        'icon512' => ['file' => 'icon-512.png', 'size' => 512, 'ratio' => 0.92],
        'maskable192' => ['file' => 'icon-maskable-192.png', 'size' => 192, 'ratio' => 0.68],
        'maskable512' => ['file' => 'icon-maskable-512.png', 'size' => 512, 'ratio' => 0.68],
    ];

    private static ?string $binary = null;

    private static bool $resolvedBinary = false;

    public static function supported(): bool
    {
        return extension_loaded('gd') || self::binary() !== null;
    }

    /**
     * Render the square brand assets beside the stored logo.
     *
     * @param  string  $source  Absolute path to the uploaded logo.
     * @param  string  $directory  Storage-relative directory that holds the logo.
     * @return array{mark: string, favicon32: string, favicon48: string, appleTouch: string, icon192: string, icon512: string, maskable192: string, maskable512: string}|null
     */
    public static function generate(string $source, string $directory): ?array
    {
        if (! is_file($source)) {
            return null;
        }

        $binary = self::binary();
        if ($binary === null && ! extension_loaded('gd')) {
            Log::warning('Chamber logo saved without derived icons: enable the GD extension or install ImageMagick.');

            return null;
        }

        $directory = trim($directory, '/');
        $disk = MediaStorage::disk();
        $workspace = storage_path('app/brand-assets/'.Str::random(16));
        $uploaded = [];

        File::ensureDirectoryExists($workspace);

        try {
            $assets = [];

            foreach (self::SQUARE as $key => $spec) {
                $relative = $directory.'/'.$spec['file'];
                $local = $workspace.'/'.$spec['file'];
                $inner = max(1, (int) round($spec['size'] * $spec['ratio']));

                self::render($source, $local, $spec['size'], $spec['size'], $inner, $inner);
                self::upload($disk, $local, $relative);
                $uploaded[] = $relative;
                $assets[$key] = $relative;
            }

            return $assets;
        } catch (Throwable $exception) {
            foreach ($uploaded as $relative) {
                try {
                    $disk->delete($relative);
                } catch (Throwable) {
                    // Best-effort cleanup; the caller drops the asset setting anyway.
                }
            }

            Log::warning('Chamber logo saved without derived icons: '.$exception->getMessage());

            return null;
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    private static function upload(FilesystemAdapter $disk, string $local, string $relative): void
    {
        $stream = fopen($local, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Could not read '.basename($local).'.');
        }

        try {
            if (! $disk->put($relative, $stream)) {
                throw new \RuntimeException('Could not store '.$relative.' on the uploads disk.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Resolve stored asset paths to public URLs for the head tags and manifest.
     *
     * @param  array<string, mixed>  $assets
     * @return array<string, mixed>
     */
    public static function urls(array $assets): array
    {
        $urls = [];

        foreach ($assets as $key => $value) {
            if (is_string($value)) {
                $urls[$key] = MediaStorage::url($value);
            }
        }

        return $urls;
    }

    public static function delete(?string $logoPath): void
    {
        if ($logoPath === null || ! str_starts_with($logoPath, 'branding/')) {
            return;
        }

        $disk = MediaStorage::disk();
        $directory = dirname($logoPath);

        if ($directory === 'branding') {
            $disk->delete($logoPath);

            return;
        }

        $disk->deleteDirectory($directory);
    }

    /**
     * Render a logo into an opaque, white canvas of the requested pixel size.
     *
     * @param  int  $canvasWidth  Target canvas width in pixels.
     * @param  int  $canvasHeight  Target canvas height in pixels.
     * @param  int  $boxWidth  Maximum artwork width in pixels.
     * @param  int  $boxHeight  Maximum artwork height in pixels.
     */
    private static function render(string $source, string $target, int $canvasWidth, int $canvasHeight, int $boxWidth, int $boxHeight): void
    {
        $binary = self::binary();

        if ($binary !== null) {
            // Scale into the safe box and centre on a white canvas.
            $command = [
                $binary, $source,
                '-background', 'white', '-gravity', 'center',
                '-resize', $boxWidth.'x'.$boxHeight,
                '-extent', $canvasWidth.'x'.$canvasHeight,
                '-strip', $target,
            ];

            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($target)) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'ImageMagick could not render '.basename($target).'.');
            }

            return;
        }

        self::renderWithGd($source, $target, $canvasWidth, $canvasHeight, $boxWidth, $boxHeight);
    }

    private static function renderWithGd(string $source, string $target, int $canvasWidth, int $canvasHeight, int $boxWidth, int $boxHeight): void
    {
        $size = @getimagesize($source);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new \RuntimeException('The uploaded logo could not be read.');
        }

        [$width, $height, $type] = $size;

        $artwork = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        if ($artwork === false) {
            throw new \RuntimeException('This image format cannot be processed.');
        }

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        imagealphablending($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, imagecolorallocate($canvas, 255, 255, 255));

        $scale = min($boxWidth / $width, $boxHeight / $height);
        $drawWidth = max(1, (int) round($width * $scale));
        $drawHeight = max(1, (int) round($height * $scale));

        imagecopyresampled(
            $canvas,
            $artwork,
            (int) round(($canvasWidth - $drawWidth) / 2),
            (int) round(($canvasHeight - $drawHeight) / 2),
            0,
            0,
            $drawWidth,
            $drawHeight,
            $width,
            $height,
        );

        imagepng($canvas, $target, 6);
        imagedestroy($canvas);
        imagedestroy($artwork);
    }

    private static function binary(): ?string
    {
        if (self::$resolvedBinary) {
            return self::$binary;
        }

        self::$resolvedBinary = true;

        foreach (['magick', 'convert'] as $candidate) {
            try {
                $process = new Process([$candidate, '-version']);
                $process->setTimeout(5);
                $process->run();

                if ($process->isSuccessful()) {
                    return self::$binary = $candidate;
                }
            } catch (Throwable) {
                // Try the next binary; the GD path still covers hosts without ImageMagick.
            }
        }

        return self::$binary = null;
    }
}
