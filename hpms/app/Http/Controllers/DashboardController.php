<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        $today = CarbonImmutable::today(config('app.timezone'))->toDateString();
        $currentMonth = CarbonImmutable::today(config('app.timezone'))->format('Y-m');
        $monthStart = CarbonImmutable::today(config('app.timezone'))->startOfMonth()->toDateString();
        $monthEnd = CarbonImmutable::today(config('app.timezone'))->endOfMonth()->toDateString();
        $months = collect(range(5, 0))->map(fn (int $offset) => CarbonImmutable::today(config('app.timezone'))->startOfMonth()->subMonths($offset));
        $monthSummaries = $reports->monthlySummaries($months->map(fn (CarbonImmutable $month): string => $month->format('Y-m'))->all());
        $daily = $reports->summary($today, $today);
        $monthly = $monthSummaries[$currentMonth] ?? $reports->summary($monthStart, $monthEnd);
        $daily['inventory_value'] = $monthly['inventory_value'];
        $metrics = [
            'medicines' => Medicine::query()->count(),
            'units' => (int) Medicine::query()->sum('stock_quantity'),
            'low_stock' => Medicine::query()->whereColumn('stock_quantity', '<=', 'minimum_stock')->where('stock_quantity', '>', 0)->count(),
            'out_of_stock' => Medicine::query()->where('stock_quantity', 0)->count(),
        ];
        $chart = $months->map(function (CarbonImmutable $month) use ($monthSummaries): array {
            $data = $monthSummaries[$month->format('Y-m')] ?? ['sales' => 0, 'purchases' => 0, 'expenses' => 0, 'net_profit' => 0];

            return ['label' => localized_date($month, 'M Y'), 'sales' => $data['sales'], 'purchases' => $data['purchases'], 'expenses' => $data['expenses'], 'profit' => $data['net_profit']];
        });
        $activity = collect()
            ->merge(Sale::query()->latest('sale_date')->limit(5)->get()->map(fn (Sale $sale): array => ['label' => $sale->sale_number, 'detail' => __('dashboard.activity_sale', ['amount' => money($sale->total_amount)]), 'date' => $sale->sale_date, 'url' => route('sales.show', $sale)]))
            ->merge(Purchase::query()->latest('purchase_date')->limit(5)->get()->map(fn (Purchase $purchase): array => ['label' => $purchase->purchase_number, 'detail' => __('dashboard.activity_purchase', ['amount' => money($purchase->total_amount)]), 'date' => $purchase->purchase_date, 'url' => route('purchases.show', $purchase)]))
            ->merge(Expense::query()->latest('expense_date')->limit(5)->get()->map(fn (Expense $expense): array => ['label' => $expense->description, 'detail' => __('dashboard.activity_expense', ['amount' => money($expense->amount)]), 'date' => $expense->expense_date, 'url' => route('expenses.index')]))
            ->merge(StockMovement::query()->with('medicine')->latest('movement_date')->limit(5)->get()->map(fn (StockMovement $movement): array => ['label' => $movement->medicine->name, 'detail' => __('dashboard.activity_stock', ['type' => default_label($movement->movement_type, 'movements'), 'quantity' => localized_number(abs($movement->quantity))]), 'date' => $movement->movement_date, 'url' => route('reports.stock-movements')]))
            ->sortByDesc(fn (array $item): string => (string) $item['date'])
            ->take(8)->values();

        return view('dashboard.index', compact('daily', 'monthly', 'metrics', 'chart', 'activity'));
    }
}
