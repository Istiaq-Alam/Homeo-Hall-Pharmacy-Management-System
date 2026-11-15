<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app()->setLocale('bn');

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
    'engine' => 'chromium',
    'fontBase' => 'file://'.str_replace(' ', '%20', public_path()),
])->render();

file_put_contents('/tmp/report-bn.html', $html);
echo strlen($html)." bytes\n";
