<?php

use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/health', function () {
    try {
        DB::select('select 1');

        return response()->json(['status' => 'ok', 'database' => 'ok']);
    } catch (Throwable) {
        return response()->json(['status' => 'unavailable', 'database' => 'unavailable'], 503);
    }
})->name('health');

Route::get('/manifest.webmanifest', function () {
    $name = 'Homeo-Hall';

    try {
        $user = auth()->user();
        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'chamber_id') && $user?->chamber_id) {
            $name = Setting::query()
                ->withoutGlobalScope('chamber')
                ->where('chamber_id', $user->chamber_id)
                ->where('key', 'chamber_name')
                ->value('value') ?: $name;
        }
    } catch (Throwable) {
        // Keep the install manifest available even when the database is temporarily down.
    }

    $shortName = mb_strlen($name) > 12 ? mb_substr($name, 0, 12).'…' : $name;

    return response()->json([
        'id' => '/',
        'name' => $name.' · Chamber management',
        'short_name' => $shortName,
        'description' => 'Private homeopathic chamber inventory, sales, purchases, expenses, and reports.',
        'start_url' => '/dashboard',
        'scope' => '/',
        'display' => 'standalone',
        'display_override' => ['standalone', 'minimal-ui'],
        'orientation' => 'any',
        'background_color' => '#f5f5f7',
        'theme_color' => '#f5f5f7',
        'lang' => 'en',
        'dir' => 'ltr',
        'categories' => ['medical', 'business', 'productivity'],
        'prefer_related_applications' => false,
        'launch_handler' => ['client_mode' => 'navigate-existing'],
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
        'shortcuts' => [
            ['name' => 'Record a sale', 'short_name' => 'New sale', 'url' => '/sales/create', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
            ['name' => 'Medicines', 'short_name' => 'Medicines', 'url' => '/medicines', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
            ['name' => 'Reports', 'short_name' => 'Reports', 'url' => '/reports', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
        ],
    ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ->header('Content-Type', 'application/manifest+json')
        ->header('Cache-Control', 'private, no-cache, must-revalidate');
})->name('manifest');

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/medicines', [MedicineController::class, 'index'])->name('medicines.index');
    Route::get('/medicines/create', [MedicineController::class, 'create'])->name('medicines.create');
    Route::post('/medicines', [MedicineController::class, 'store'])->name('medicines.store');
    Route::get('/medicines/categories', [MedicineController::class, 'categories'])->name('medicines.categories');
    Route::post('/medicines/categories', [MedicineController::class, 'saveCategory'])->name('medicines.categories.store');
    Route::put('/medicines/categories/{category}', [MedicineController::class, 'saveCategory'])->name('medicines.categories.update');
    Route::get('/medicines/adjustments/create', [MedicineController::class, 'adjustmentForm'])->name('medicines.adjustments.create');
    Route::post('/medicines/adjustments', [MedicineController::class, 'adjust'])->name('medicines.adjustments.store');
    Route::get('/medicines/{medicine}/edit', [MedicineController::class, 'edit'])->name('medicines.edit');
    Route::put('/medicines/{medicine}', [MedicineController::class, 'update'])->name('medicines.update');
    Route::delete('/medicines/{medicine}', [MedicineController::class, 'deactivate'])->name('medicines.deactivate');
    Route::get('/medicines/{medicine}', [MedicineController::class, 'show'])->name('medicines.show');

    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');

    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/medicine-price', [SaleController::class, 'price'])->name('sales.price');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/categories', [ExpenseController::class, 'categories'])->name('expenses.categories');
    Route::post('/expenses/categories', [ExpenseController::class, 'saveCategory'])->name('expenses.categories.store');
    Route::put('/expenses/categories/{category}', [ExpenseController::class, 'saveCategory'])->name('expenses.categories.update');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::post('/expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/yearly', [ReportController::class, 'yearly'])->name('reports.yearly');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('/reports/medicine-sales', [ReportController::class, 'medicineSales'])->name('reports.medicine-sales');
    Route::get('/reports/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
    Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
    Route::get('/reports/stock-movements', [ReportController::class, 'stockMovements'])->name('reports.stock-movements');
    Route::get('/reports/export.csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('/reports/export.pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/export.xlsx', [ReportController::class, 'xlsx'])->name('reports.xlsx');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/admin-accounts', [AdminAccountController::class, 'store'])->name('admin-accounts.store');
    Route::get('/settings/backup', [BackupController::class, 'download'])->name('settings.backup');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [SettingsController::class, 'password'])->name('settings.password');
});
