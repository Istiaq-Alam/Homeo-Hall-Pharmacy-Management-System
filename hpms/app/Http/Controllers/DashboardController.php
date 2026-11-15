<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        $today = CarbonImmutable::today(config('app.timezone'))->toDateString();
        $monthStart = CarbonImmutable::today(config('app.timezone'))->startOfMonth()->toDateString();
        $monthEnd = CarbonImmutable::today(config('app.timezone'))->endOfMonth()->toDateString();
        $daily = $reports->summary($today, $today);
        $monthly = $reports->summary($monthStart, $monthEnd);
        $daily['inventory_value'] = $monthly['inventory_value'];
        $metrics = [
            'medicines' => Medicine::query()->count(),
            'units' => (int) Medicine::query()->sum('stock_quantity'),
            'low_stock' => Medicine::query()->whereColumn('stock_quantity', '<=', 'minimum_stock')->where('stock_quantity', '>', 0)->count(),
            'out_of_stock' => Medicine::query()->where('stock_quantity', 0)->count(),
        ];
        $months = collect(range(5, 0))->map(fn (int $offset) => CarbonImmutable::today(config('app.timezone'))->startOfMonth()->subMonths($offset));
        $chart = $months->map(function (CarbonImmutable $month) use ($reports): array {
            $data = $reports->summary($month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString());
            return ['label' => $month->format('M Y'), 'sales' => $data['sales'], 'purchases' => $data['purchases'], 'expenses' => $data['expenses'], 'profit' => $data['net_profit']];
        });
        $activity = collect()
            ->merge(Sale::query()->latest('sale_date')->limit(5)->get()->map(fn (Sale $sale): array => ['label' => $sale->sale_number, 'detail' => 'Sale · ৳'.number_format((float) $sale->total_amount, 2), 'date' => $sale->sale_date, 'url' => route('sales.show', $sale)]))
            ->merge(Purchase::query()->latest('purchase_date')->limit(5)->get()->map(fn (Purchase $purchase): array => ['label' => $purchase->purchase_number, 'detail' => 'Purchase · ৳'.number_format((float) $purchase->total_amount, 2), 'date' => $purchase->purchase_date, 'url' => route('purchases.show', $purchase)]))
            ->merge(Expense::query()->latest('expense_date')->limit(5)->get()->map(fn (Expense $expense): array => ['label' => $expense->description, 'detail' => 'Expense · ৳'.number_format((float) $expense->amount, 2), 'date' => $expense->expense_date, 'url' => route('expenses.index')]))
            ->merge(StockMovement::query()->with('medicine')->latest('movement_date')->limit(5)->get()->map(fn (StockMovement $movement): array => ['label' => $movement->medicine->name, 'detail' => 'Stock · '.$movement->movement_type.' · '.abs($movement->quantity), 'date' => $movement->movement_date, 'url' => route('reports.stock-movements')]))
            ->sortByDesc(fn (array $item): string => (string) $item['date'])
            ->take(8)->values();

        return view('dashboard.index', compact('daily', 'monthly', 'metrics', 'chart', 'activity'));
    }
}
