<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Renders application HTML to a PDF.
 *
 * WeasyPrint is preferred: it shapes complex scripts through Pango/HarfBuzz,
 * which Bengali (and other Indic scripts) need for correct conjuncts and vowel
 * signs, and it starts in about a second instead of the several seconds a
 * headless browser needs. Chromium is the next choice, then Dompdf, which is
 * dependency-free but draws those scripts in logical order without shaping.
 */
class PdfRenderer
{
    /** Engines that shape complex scripts, in the order they are tried. */
    private const SHAPING_ENGINES = ['weasyprint', 'chromium'];

    /** @var array<int, string> */
    private const BROWSER_NAMES = [
        'google-chrome',
        'google-chrome-stable',
        'chromium',
        'chromium-browser',
        'microsoft-edge',
        'microsoft-edge-stable',
    ];

    private ?string $resolvedBinary = null;

    private bool $binaryResolved = false;

    private ?string $resolvedWeasyPrint = null;

    private bool $weasyPrintResolved = false;

    /**
     * The engine that would render a document right now: "weasyprint",
     * "chromium" or "dompdf".
     */
    public function driver(): string
    {
        return $this->engines()[0];
    }

    /**
     * Engines to try, best first. A configured engine that is not installed is
     * skipped so a misconfiguration degrades instead of breaking every export.
     *
     * @return list<string>
     */
    private function engines(): array
    {
        $configured = strtolower(trim((string) config('pdf.driver', 'auto')));

        if ($configured === 'dompdf') {
            return ['dompdf'];
        }

        $available = array_values(array_filter(
            self::SHAPING_ENGINES,
            fn (string $engine): bool => $this->engineBinary($engine) !== null,
        ));

        if ($configured === 'auto') {
            return [...$available, 'dompdf'];
        }

        if (in_array($configured, self::SHAPING_ENGINES, true)) {
            if (in_array($configured, $available, true)) {
                // Keep the configured engine first, but still allow a fallback.
                return [$configured, ...array_values(array_filter($available, fn (string $engine): bool => $engine !== $configured)), 'dompdf'];
            }

            Log::warning('PDF_DRIVER is set to '.$configured.', but no such binary was found. Falling back to '.($available[0] ?? 'Dompdf').'.');
        }

        return [...$available, 'dompdf'];
    }

    /**
     * Base location the report view should use for its local font files.
     */
    public function assetBasePath(): string
    {
        return in_array($this->driver(), self::SHAPING_ENGINES, true)
            ? $this->fileUrl(public_path())
            : public_path();
    }

    /**
     * Render a full HTML document and return the PDF bytes.
     */
    public function render(string $html): string
    {
        $engines = $this->engines();
        $last = count($engines) - 1;

        foreach ($engines as $index => $engine) {
            try {
                return match ($engine) {
                    'weasyprint' => $this->renderWithWeasyPrint($html),
                    'chromium' => $this->renderWithBrowser($html),
                    default => $this->renderWithDompdf($html),
                };
            } catch (Throwable $exception) {
                if ($index === $last || ! config('pdf.fallback', true)) {
                    throw $exception;
                }

                Log::warning('PDF rendering with '.$engine.' failed; trying the next engine.', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        throw new RuntimeException('No PDF engine is available.');
    }

    /**
     * Binary for a shaping engine, or null when it is not installed.
     */
    private function engineBinary(string $engine): ?string
    {
        return $engine === 'weasyprint' ? $this->weasyPrintBinary() : $this->browserBinary();
    }

    /**
     * Location of the WeasyPrint binary, or null when it is not installed.
     */
    public function weasyPrintBinary(): ?string
    {
        if ($this->weasyPrintResolved) {
            return $this->resolvedWeasyPrint;
        }

        $this->weasyPrintResolved = true;
        $configured = trim((string) config('pdf.weasyprint'));

        if ($configured !== '') {
            return $this->resolvedWeasyPrint = is_file($configured) && is_executable($configured) ? $configured : null;
        }

        foreach (['weasyprint'] as $name) {
            if ($found = (new ExecutableFinder)->find($name)) {
                return $this->resolvedWeasyPrint = $found;
            }
        }

        return $this->resolvedWeasyPrint = null;
    }

    private function renderWithWeasyPrint(string $html): string
    {
        $binary = $this->weasyPrintBinary();

        if ($binary === null) {
            throw new RuntimeException('WeasyPrint is not installed.');
        }

        $directory = storage_path('app/pdf');
        File::ensureDirectoryExists($directory);

        $token = Str::random(20);
        $htmlPath = $directory.DIRECTORY_SEPARATOR.$token.'.html';
        $pdfPath = $directory.DIRECTORY_SEPARATOR.$token.'.pdf';

        File::put($htmlPath, $html);

        try {
            $process = new Process([
                $binary,
                '--encoding', 'utf-8',
                '--media-type', 'print',
                '--base-url', $this->fileUrl(public_path()).'/',
                $htmlPath,
                $pdfPath,
            ]);

            $process->setTimeout((float) config('pdf.timeout', 60));
            // WeasyPrint writes rendering warnings to stderr; a non-zero exit is
            // what actually signals failure.
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('WeasyPrint exited with status '.$process->getExitCode().': '.trim($process->getErrorOutput()));
            }

            if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
                throw new RuntimeException('WeasyPrint did not write a PDF file.');
            }

            $bytes = (string) file_get_contents($pdfPath);

            if (! str_starts_with($bytes, '%PDF-')) {
                throw new RuntimeException('WeasyPrint output is not a PDF document.');
            }

            return $bytes;
        } finally {
            File::delete([$htmlPath, $pdfPath]);
        }
    }

    /**
     * Location of the browser binary, or null when none can be found.
     */
    public function browserBinary(): ?string
    {
        if ($this->binaryResolved) {
            return $this->resolvedBinary;
        }

        $this->binaryResolved = true;
        $this->resolvedBinary = $this->findBrowserBinary();

        return $this->resolvedBinary;
    }

    private function findBrowserBinary(): ?string
    {
        $configured = trim((string) config('pdf.binary'));

        if ($configured !== '') {
            return is_file($configured) && is_executable($configured) ? $configured : null;
        }

        $finder = new ExecutableFinder;

        foreach (self::BROWSER_NAMES as $name) {
            if ($found = $finder->find($name)) {
                return $found;
            }
        }

        $candidates = [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
        ];

        foreach (['PROGRAMFILES', 'PROGRAMFILES(X86)', 'LOCALAPPDATA'] as $variable) {
            $base = getenv($variable);

            if (! is_string($base) || $base === '') {
                continue;
            }

            $candidates[] = $base.'\\Google\\Chrome\\Application\\chrome.exe';
            $candidates[] = $base.'\\Microsoft\\Edge\\Application\\msedge.exe';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function renderWithBrowser(string $html): string
    {
        $binary = $this->browserBinary();

        if ($binary === null) {
            throw new RuntimeException('No Chrome/Chromium binary is available for PDF rendering.');
        }

        $directory = storage_path('app/pdf');
        File::ensureDirectoryExists($directory);

        $token = Str::random(20);
        $htmlPath = $directory.DIRECTORY_SEPARATOR.$token.'.html';
        $pdfPath = $directory.DIRECTORY_SEPARATOR.$token.'.pdf';
        $profilePath = $directory.DIRECTORY_SEPARATOR.$token.'-profile';

        File::put($htmlPath, $html);

        try {
            // The document is generated by the application itself; --no-sandbox keeps
            // headless Chromium working where kernel/user namespaces are unavailable.
            $process = new Process([
                $binary,
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--hide-scrollbars',
                '--mute-audio',
                '--no-first-run',
                '--no-default-browser-check',
                '--no-pdf-header-footer',
                '--font-render-hinting=none',
                '--run-all-compositor-stages-before-draw',
                '--user-data-dir='.$profilePath,
                '--virtual-time-budget=8000',
                '--print-to-pdf='.$pdfPath,
                $this->fileUrl($htmlPath),
            ]);

            $process->setTimeout((float) config('pdf.timeout', 60));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Chromium exited with status '.$process->getExitCode().': '.trim($process->getErrorOutput()));
            }

            if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
                throw new RuntimeException('Chromium did not write a PDF file.');
            }

            $bytes = (string) file_get_contents($pdfPath);

            if (! str_starts_with($bytes, '%PDF-')) {
                throw new RuntimeException('Chromium output is not a PDF document.');
            }

            return $bytes;
        } finally {
            File::delete([$htmlPath, $pdfPath]);
            File::deleteDirectory($profilePath);
        }
    }

    private function renderWithDompdf(string $html): string
    {
        $pdf = app('dompdf.wrapper')->loadHTML($html);
        $pdf->setPaper('a4', 'portrait');

        return (string) $pdf->output();
    }

    private function fileUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $segments = array_map(
            fn (string $segment): string => preg_match('/^[A-Za-z]:$/', $segment) ? $segment : rawurlencode($segment),
            explode('/', $path),
        );

        return 'file://'.implode('/', $segments);
    }
}
