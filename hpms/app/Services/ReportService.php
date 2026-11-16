<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** @return array<string, mixed> */
    public function summary(string $from, string $to): array
    {
        $start = $from.' 00:00:00';
        $end = $to.' 23:59:59';
        $sales = Sale::query()->where('status', 'completed')->whereBetween('sale_date', [$start, $end]);
        $purchases = Purchase::query()->where('status', 'completed')->whereBetween('purchase_date', [$start, $end]);
        $expenses = Expense::query()->where('status', 'completed')->whereBetween('expense_date', [$start, $end]);
        $saleItems = SaleItem::query()->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('sale_date', [$start, $end]));
        $purchaseItems = PurchaseItem::query()->whereHas('purchase', fn ($query) => $query->where('status', 'completed')->whereBetween('purchase_date', [$start, $end]));

        $salesAggregate = (clone $sales)->selectRaw('COALESCE(SUM(total_amount), 0) as revenue, COUNT(*) as transaction_count')->toBase()->first();
        $purchasesAggregate = (clone $purchases)->selectRaw('COALESCE(SUM(total_amount), 0) as total, COUNT(*) as transaction_count')->toBase()->first();
        $expensesAggregate = (clone $expenses)->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as transaction_count')->toBase()->first();
        $saleItemsAggregate = (clone $saleItems)->selectRaw('COALESCE(SUM(line_cost_total), 0) as cogs, COALESCE(SUM(quantity), 0) as units')->toBase()->first();
        $purchaseItemsAggregate = (clone $purchaseItems)->selectRaw('COALESCE(SUM(quantity), 0) as units')->toBase()->first();

        $revenue = (float) ($salesAggregate->revenue ?? 0);
        $purchaseTotal = (float) ($purchasesAggregate->total ?? 0);
        $expenseTotal = (float) ($expensesAggregate->total ?? 0);
        $cogs = (float) ($saleItemsAggregate->cogs ?? 0);
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
            'sales_count' => (int) ($salesAggregate->transaction_count ?? 0),
            'purchases_count' => (int) ($purchasesAggregate->transaction_count ?? 0),
            'expenses_count' => (int) ($expensesAggregate->transaction_count ?? 0),
            'units_sold' => (int) ($saleItemsAggregate->units ?? 0),
            'units_purchased' => (int) ($purchaseItemsAggregate->units ?? 0),
            'inventory_value' => round((float) Medicine::query()->sum(DB::raw('stock_quantity * average_cost')), 2),
        ];
    }

    /**
     * Build several month summaries with one grouped query per ledger table.
     *
     * @param  array<int, string>  $months  Y-m keys
     * @return array<string, array<string, mixed>>
     */
    public function monthlySummaries(array $months): array
    {
        $months = collect($months)->filter()->unique()->sort()->values()->all();
        if ($months === []) {
            return [];
        }

        $start = $months[0].'-01 00:00:00';
        $lastMonth = $months[array_key_last($months)];
        $end = CarbonImmutable::parse($lastMonth.'-01')->endOfMonth()->toDateTimeString();

        $salesSelects = [];
        $purchaseSelects = [];
        $expenseSelects = [];
        $saleItemSelects = [];
        $purchaseItemSelects = [];
        $salesBindings = [];
        $purchaseBindings = [];
        $expenseBindings = [];
        $saleItemBindings = [];
        $purchaseItemBindings = [];

        foreach ($months as $index => $month) {
            $monthStart = $month.'-01 00:00:00';
            $monthEnd = CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateTimeString();

            $salesSelects[] = "COALESCE(SUM(CASE WHEN sale_date BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as m{$index}_sales";
            $salesSelects[] = "COALESCE(SUM(CASE WHEN sale_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as m{$index}_count";
            $salesBindings = [...$salesBindings, $monthStart, $monthEnd, $monthStart, $monthEnd];

            $purchaseSelects[] = "COALESCE(SUM(CASE WHEN purchase_date BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as m{$index}_purchases";
            $purchaseSelects[] = "COALESCE(SUM(CASE WHEN purchase_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as m{$index}_count";
            $purchaseBindings = [...$purchaseBindings, $monthStart, $monthEnd, $monthStart, $monthEnd];

            $expenseSelects[] = "COALESCE(SUM(CASE WHEN expense_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) as m{$index}_expenses";
            $expenseSelects[] = "COALESCE(SUM(CASE WHEN expense_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as m{$index}_count";
            $expenseBindings = [...$expenseBindings, $monthStart, $monthEnd, $monthStart, $monthEnd];

            $saleItemSelects[] = "COALESCE(SUM(CASE WHEN sales.sale_date BETWEEN ? AND ? THEN sale_items.line_cost_total ELSE 0 END), 0) as m{$index}_cogs";
            $saleItemSelects[] = "COALESCE(SUM(CASE WHEN sales.sale_date BETWEEN ? AND ? THEN sale_items.quantity ELSE 0 END), 0) as m{$index}_units";
            $saleItemBindings = [...$saleItemBindings, $monthStart, $monthEnd, $monthStart, $monthEnd];

            $purchaseItemSelects[] = "COALESCE(SUM(CASE WHEN purchases.purchase_date BETWEEN ? AND ? THEN purchase_items.quantity ELSE 0 END), 0) as m{$index}_units";
            $purchaseItemBindings = [...$purchaseItemBindings, $monthStart, $monthEnd];
        }

        $salesRow = Sale::query()->where('status', 'completed')->whereBetween('sale_date', [$start, $end])
            ->selectRaw(implode(', ', $salesSelects), $salesBindings)->toBase()->first();
        $purchaseRow = Purchase::query()->where('status', 'completed')->whereBetween('purchase_date', [$start, $end])
            ->selectRaw(implode(', ', $purchaseSelects), $purchaseBindings)->toBase()->first();
        $expenseRow = Expense::query()->where('status', 'completed')->whereBetween('expense_date', [$start, $end])
            ->selectRaw(implode(', ', $expenseSelects), $expenseBindings)->toBase()->first();
        $saleItemRow = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->selectRaw(implode(', ', $saleItemSelects), $saleItemBindings)->toBase()->first();
        $purchaseItemRow = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'completed')
            ->whereBetween('purchases.purchase_date', [$start, $end])
            ->selectRaw(implode(', ', $purchaseItemSelects), $purchaseItemBindings)->toBase()->first();
        $inventoryValue = round((float) Medicine::query()->sum(DB::raw('stock_quantity * average_cost')), 2);

        $result = [];
        foreach ($months as $index => $month) {
            $sales = (float) ($salesRow->{"m{$index}_sales"} ?? 0);
            $purchases = (float) ($purchaseRow->{"m{$index}_purchases"} ?? 0);
            $expenses = (float) ($expenseRow->{"m{$index}_expenses"} ?? 0);
            $cogs = (float) ($saleItemRow->{"m{$index}_cogs"} ?? 0);
            $grossProfit = round($sales - $cogs, 2);

            $result[$month] = [
                'from' => $month.'-01',
                'to' => CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString(),
                'sales' => round($sales, 2),
                'purchases' => round($purchases, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => $grossProfit,
                'expenses' => round($expenses, 2),
                'net_profit' => round($grossProfit - $expenses, 2),
                'sales_count' => (int) ($salesRow->{"m{$index}_count"} ?? 0),
                'purchases_count' => (int) ($purchaseRow->{"m{$index}_count"} ?? 0),
                'expenses_count' => (int) ($expenseRow->{"m{$index}_count"} ?? 0),
                'units_sold' => (int) ($saleItemRow->{"m{$index}_units"} ?? 0),
                'units_purchased' => (int) ($purchaseItemRow->{"m{$index}_units"} ?? 0),
                'inventory_value' => $inventoryValue,
            ];
        }

        return $result;
    }

    /** @return Collection<int, object> */
    public function medicineSales(string $from, string $to): Collection
    {
        return SaleItem::query()
            ->selectRaw('medicine_id, SUM(quantity) as quantity_sold, SUM(line_total) as revenue, SUM(line_cost_total) as cogs')
            ->with('medicine.category')
            ->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('sale_date', [$from.' 00:00:00', $to.' 23:59:59']))
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

    public function movements(string $from, string $to): Builder
    {
        return StockMovement::query()->with('medicine')->whereBetween('movement_date', [$from.' 00:00:00', $to.' 23:59:59'])->latest('movement_date');
    }
}
