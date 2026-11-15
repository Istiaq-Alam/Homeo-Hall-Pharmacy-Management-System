<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Derives the install identity (favicons, PWA icons, Apple touch icon and iOS
 * startup screens) from a chamber's uploaded logo, so each chamber can
 * white-label the app without shipping artwork per tenant.
 *
 * GD is used when the extension is loaded; otherwise the ImageMagick binary is
 * invoked. Without either backend the caller keeps the bundled Homeo-Hall
 * assets, so a logo upload never fails because of image processing.
 */
class BrandAssets
{
    /**
     * iOS startup screens as CSS width, CSS height and device pixel ratio.
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

    /** Chamber name colour on the generated splash screens. */
    private const LABEL_COLOR = '#111827';

    /** Average glyph advance (in em) used to size the chamber name. */
    private const LABEL_ADVANCE = 0.72;

    private static ?string $binary = null;

    private static bool $resolvedBinary = false;

    public static function supported(): bool
    {
        return extension_loaded('gd') || self::binary() !== null;
    }

    /**
     * Render every brand asset beside the stored logo.
     *
     * @param  string  $source  Absolute path to the uploaded logo.
     * @param  string  $directory  Storage-relative directory that holds the logo.
     * @param  string|null  $label  Chamber name printed under the logo on the splash screens.
     * @return array{mark: string, favicon32: string, favicon48: string, appleTouch: string, icon192: string, icon512: string, maskable192: string, maskable512: string, splash: array<string, string>}|null
     */
    public static function generate(string $source, string $directory, ?string $label = null): ?array
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

        File::ensureDirectoryExists($workspace.'/splash');

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

            $assets['splash'] = [];

            foreach (self::SPLASH as [$width, $height, $dpr]) {
                $pixelWidth = $width * $dpr;
                $pixelHeight = $height * $dpr;
                $relative = $directory.'/splash/'.$pixelWidth.'x'.$pixelHeight.'.png';
                $local = $workspace.'/splash/'.$pixelWidth.'x'.$pixelHeight.'.png';

                $layout = self::splashLayout($pixelWidth, $pixelHeight, $source, $label);

                self::render($source, $local, $pixelWidth, $pixelHeight, $layout['artWidth'], $layout['artHeight'], $layout);
                self::upload($disk, $local, $relative);
                $uploaded[] = $relative;
                $assets['splash'][$pixelWidth.'x'.$pixelHeight] = $relative;
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

    /**
     * Splash geometry: artwork and chamber name are centred as one lockup, the
     * way the bundled Homeo-Hall startup screens are composed.
     *
     * @return array{artWidth: int, artHeight: int, artTop: int, label: string|null, labelSize: int, labelTop: int}
     */
    private static function splashLayout(int $canvasWidth, int $canvasHeight, ?string $source, ?string $label): array
    {
        $portrait = $canvasHeight >= $canvasWidth;

        [$artWidth, $artHeight] = self::fitArtwork(
            $source,
            (int) round($canvasWidth * ($portrait ? 0.52 : 0.34)),
            (int) round($canvasHeight * ($portrait ? 0.24 : 0.38)),
        );

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $label));
        $maxTextWidth = (int) round($canvasWidth * 0.84);
        $baseSize = max(12, min(96, (int) round($canvasWidth * ($portrait ? 0.05 : 0.032))));
        // Long chamber names step down instead of spanning the whole screen.
        $fittedSize = (int) floor($maxTextWidth / (self::LABEL_ADVANCE * max(1, mb_strlen($text))));
        $labelSize = max((int) round($baseSize * 0.45), min($baseSize, $fittedSize));
        $labelText = self::fitLabel($text, $labelSize, $maxTextWidth);
        $gap = $labelText === null ? 0 : (int) round($canvasHeight * 0.03);
        $textHeight = $labelText === null ? 0 : (int) round($labelSize * 1.35);

        $artTop = max(0, (int) round(($canvasHeight - ($artHeight + $gap + $textHeight)) * 0.46));

        return [
            'artWidth' => $artWidth,
            'artHeight' => $artHeight,
            'artTop' => $artTop,
            'label' => $labelText,
            'labelSize' => $labelSize,
            'labelTop' => $artTop + $artHeight + $gap,
        ];
    }

    /**
     * Largest artwork rectangle that fits the splash box without distortion.
     *
     * @return array{0: int, 1: int}
     */
    private static function fitArtwork(?string $source, int $maxWidth, int $maxHeight): array
    {
        $size = $source === null ? false : @getimagesize($source);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return [max(1, $maxWidth), max(1, $maxHeight)];
        }

        $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);

        return [max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale))];
    }

    /**
     * Font for the chamber name. The bundled Noto file only carries Bengali
     * glyphs, so Latin names use DejaVu from the system (shipped with the image).
     */
    private static function labelFont(string $label): ?string
    {
        $candidates = preg_match('/\p{Bengali}/u', $label) === 1
            ? [public_path('fonts/NotoSansBengali-Bold.ttf'), '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']
            : ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', public_path('fonts/DejaVuSans-Bold.ttf')];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Trim the chamber name to what fits the canvas width in one line.
     *
     * Both render backends share this estimate so a name is cut the same way
     * whether GD or ImageMagick draws it; the crop is deliberately generous.
     */
    private static function fitLabel(string $label, int $fontSize, int $maxWidth): ?string
    {
        $label = trim($label);

        if ($label === '') {
            return null;
        }

        $chars = mb_str_split($label);
        $approximateWidth = fn (int $count): int => (int) round($count * $fontSize * self::LABEL_ADVANCE);

        if ($approximateWidth(count($chars)) <= $maxWidth) {
            return $label;
        }

        while (count($chars) > 1 && $approximateWidth(count($chars) + 1) > $maxWidth) {
            array_pop($chars);
        }

        return rtrim(implode('', $chars)).'…';
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

        if (isset($assets['splash']) && is_array($assets['splash'])) {
            $urls['splash'] = [];

            foreach ($assets['splash'] as $size => $path) {
                if (is_string($path)) {
                    $urls['splash'][$size] = MediaStorage::url($path);
                }
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
    private static function render(string $source, string $target, int $canvasWidth, int $canvasHeight, int $boxWidth, int $boxHeight, ?array $layout = null): void
    {
        $binary = self::binary();

        if ($binary !== null) {
            if ($layout === null) {
                // Square icons: scale into the safe box and centre on a white canvas.
                $command = [
                    $binary, $source,
                    '-background', 'white', '-gravity', 'center',
                    '-resize', $boxWidth.'x'.$boxHeight,
                    '-extent', $canvasWidth.'x'.$canvasHeight,
                ];
            } else {
                $artLeft = (int) max(0, round(($canvasWidth - $layout['artWidth']) / 2));

                $command = [
                    $binary,
                    '-background', 'white',
                    '-size', $canvasWidth.'x'.$canvasHeight, 'xc:white',
                    '(', $source, '-resize', $layout['artWidth'].'x'.$layout['artHeight'].'!', ')',
                    '-gravity', 'northwest', '-geometry', '+'.$artLeft.'+'.$layout['artTop'],
                    '-composite',
                ];

                $font = $layout['label'] === null ? null : self::labelFont($layout['label']);

                if ($font !== null) {
                    $command = [...$command, '-font', $font, '-fill', self::LABEL_COLOR,
                        '-pointsize', (string) $layout['labelSize'],
                        '-gravity', 'north',
                        '-annotate', '+0+'.$layout['labelTop'], $layout['label']];
                }
            }

            $command[] = '-strip';
            $command[] = $target;

            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($target)) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'ImageMagick could not render '.basename($target).'.');
            }

            return;
        }

        self::renderWithGd($source, $target, $canvasWidth, $canvasHeight, $boxWidth, $boxHeight, $layout);
    }

    private static function renderWithGd(string $source, string $target, int $canvasWidth, int $canvasHeight, int $boxWidth, int $boxHeight, ?array $layout = null): void
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

        if ($layout === null) {
            $scale = min($boxWidth / $width, $boxHeight / $height);
            $drawWidth = max(1, (int) round($width * $scale));
            $drawHeight = max(1, (int) round($height * $scale));
            $drawX = (int) round(($canvasWidth - $drawWidth) / 2);
            $drawY = (int) round(($canvasHeight - $drawHeight) / 2);
        } else {
            $drawWidth = $layout['artWidth'];
            $drawHeight = $layout['artHeight'];
            $drawX = (int) max(0, round(($canvasWidth - $drawWidth) / 2));
            $drawY = $layout['artTop'];
        }

        imagecopyresampled(
            $canvas,
            $artwork,
            $drawX,
            $drawY,
            0,
            0,
            $drawWidth,
            $drawHeight,
            $width,
            $height,
        );

        if (($layout['label'] ?? null) !== null) {
            self::drawLabelWithGd($canvas, $layout['label'], $layout['labelSize'], $layout['labelTop'], $canvasWidth);
        }

        imagepng($canvas, $target, 6);
        imagedestroy($canvas);
        imagedestroy($artwork);
    }

    /**
     * Draw the chamber name with FreeType so it stays crisp at every splash size.
     * Without FreeType the splash still renders, just without the label.
     */
    private static function drawLabelWithGd(\GdImage $canvas, string $label, int $fontSize, int $top, int $canvasWidth): void
    {
        if (! function_exists('imagettftext')) {
            return;
        }

        $font = self::labelFont($label);

        if ($font === null) {
            return;
        }

        $maxWidth = (int) round($canvasWidth * 0.9);

        for ($size = $fontSize; $size > 12; $size -= 2) {
            $box = imagettfbbox($size, 0, $font, $label);

            if ($box !== false && ($box[2] - $box[0]) <= $maxWidth) {
                break;
            }
        }

        $box = imagettfbbox($size, 0, $font, $label);

        if ($box === false) {
            return;
        }

        $textWidth = $box[2] - $box[0];
        $colour = imagecolorallocate($canvas, 0x11, 0x18, 0x27);

        imagettftext(
            $canvas,
            $size,
            0,
            (int) max(0, round(($canvasWidth - $textWidth) / 2)),
            $top - $box[7],
            $colour,
            $font,
            $label,
        );
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
