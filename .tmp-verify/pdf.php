<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app()->setLocale('bn');

$renderer = app(App\Services\PdfRenderer::class);

fwrite(STDERR, 'driver='.$renderer->driver()
    .' binary='.var_export($renderer->browserBinary(), true)
    .' memory_limit='.ini_get('memory_limit').PHP_EOL);

$summary = [
    'from' => '2026-11-01', 'to' => '2026-11-16',
    'sales' => 12345.5, 'purchases' => 8000, 'cogs' => 4000, 'gross_profit' => 8345.5,
    'expenses' => 1200, 'net_profit' => 7145.5,
    'sales_count' => 42, 'purchases_count' => 7, 'expenses_count' => 5,
    'units_sold' => 310, 'units_purchased' => 200, 'inventory_value' => 98765.25,
];

$html = view('reports.pdf', [
    'range' => ['from' => $summary['from'], 'to' => $summary['to']],
    'summary' => $summary,
    'chamberName' => 'হোমিও-হল চেম্বার',
    'administratorName' => 'ডা. রফিকুল ইসলাম',
    'generatedAt' => now()->timezone(config('app.timezone')),
    'logoData' => null,
    'engine' => $renderer->driver(),
    'fontBase' => $renderer->assetBasePath(),
])->render();

foreach (['chromium', 'dompdf'] as $engine) {
    $start = microtime(true);

    try {
        $bytes = $engine === 'chromium'
            ? (function () use ($renderer, $html) {
                $method = new ReflectionMethod($renderer, 'renderWithBrowser');
                $method->setAccessible(true);

                return $method->invoke($renderer, $html);
            })()
            : (function () use ($renderer, $html) {
                $method = new ReflectionMethod($renderer, 'renderWithDompdf');
                $method->setAccessible(true);

                return $method->invoke($renderer, $html);
            })();

        file_put_contents('/out/bn-'.$engine.'.pdf', $bytes);
        fwrite(STDERR, sprintf("%s ok %.2fs %d bytes\n", $engine, microtime(true) - $start, strlen($bytes)));
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("%s FAIL %.2fs %s\n", $engine, microtime(true) - $start, $exception->getMessage()));
    }
}
