<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class BackupController extends Controller
{
    private const TABLES = ['medicine_categories', 'medicines', 'purchases', 'purchase_items', 'sales', 'sale_items', 'expense_categories', 'expenses', 'stock_adjustments', 'stock_movements', 'settings'];

    public function download(): Response
    {
        $path = storage_path('app/private/homeo-hall-data-'.now()->format('Ymd-His').'.zip');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create the backup archive.');
        }
        foreach (self::TABLES as $table) {
            $csv = fopen('php://temp', 'r+');
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            fputcsv($csv, $columns);
            DB::table($table)->orderBy('id')->chunk(500, function ($rows) use ($csv, $columns): void {
                foreach ($rows as $row) {
                    fputcsv($csv, array_map(fn (string $column) => $row->{$column}, $columns));
                }
            });
            rewind($csv);
            $zip->addFromString($table.'.csv', stream_get_contents($csv));
            fclose($csv);
        }
        $zip->close();

        return response()->download($path, 'homeo-hall-data-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend(true);
    }
}
