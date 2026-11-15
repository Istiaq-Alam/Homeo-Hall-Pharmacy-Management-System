<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    /** @return array<string, mixed> */
    public function summary(string $from, string $to): array
    {
        $sales = Sale::query()->where('status', 'completed')->whereBetween('sale_date', [$from, $to]);
        $purchases = Purchase::query()->where('status', 'completed')->whereBetween('purchase_date', [$from, $to]);
        $expenses = Expense::query()->where('status', 'completed')->whereBetween('expense_date', [$from, $to]);
        $revenue = (float) (clone $sales)->sum('total_amount');
        $purchaseTotal = (float) (clone $purchases)->sum('total_amount');
        $expenseTotal = (float) (clone $expenses)->sum('amount');
        $cogs = (float) SaleItem::query()->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('sale_date', [$from, $to]))->sum('line_cost_total');
        $unitsSold = (int) SaleItem::query()->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('sale_date', [$from, $to]))->sum('quantity');
        $unitsPurchased = (int) \App\Models\PurchaseItem::query()->whereHas('purchase', fn ($query) => $query->where('status', 'completed')->whereBetween('purchase_date', [$from, $to]))->sum('quantity');
        $grossProfit = round($revenue - $cogs, 2);

        return [
            'from' => $from,
            'to' => $to,
            'sales' => round($revenue, 2),
            'purchases' => round($purchaseTotal, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => $grossProfit,
            'expenses' => round($expenseTotal, 2),
            'net_profit' => round($grossProfit - $expenseTotal, 2),
            'sales_count' => (clone $sales)->count(),
            'purchases_count' => (clone $purchases)->count(),
            'expenses_count' => (clone $expenses)->count(),
            'units_sold' => $unitsSold,
            'units_purchased' => $unitsPurchased,
            'inventory_value' => round((float) Medicine::query()->sum(\Illuminate\Database\Query\Expression::raw('stock_quantity * average_cost')), 2),
        ];
    }

    /** @return Collection<int, object> */
    public function medicineSales(string $from, string $to): Collection
    {
        return SaleItem::query()
            ->selectRaw('medicine_id, SUM(quantity) as quantity_sold, SUM(line_total) as revenue, SUM(line_cost_total) as cogs')
            ->with('medicine.category')
            ->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('sale_date', [$from, $to]))
            ->groupBy('medicine_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(function (SaleItem $item): object {
                return (object) [
                    'medicine' => $item->medicine,
                    'quantity_sold' => (int) $item->quantity_sold,
                    'revenue' => (float) $item->revenue,
                    'cogs' => (float) $item->cogs,
                    'gross_profit' => round((float) $item->revenue - (float) $item->cogs, 2),
                ];
            });
    }

    /** @return Collection<int, Medicine> */
    public function inventory(): Collection
    {
        return Medicine::query()->with('category')->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    public function range(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return ['from' => $start->toDateString(), 'to' => $end->toDateString()];
    }

    /** @return Collection<int, StockMovement> */
    public function movements(string $from, string $to): Collection
    {
        return StockMovement::query()->with('medicine')->whereBetween('movement_date', [$from.' 00:00:00', $to.' 23:59:59'])->latest('movement_date')->get();
    }
}
