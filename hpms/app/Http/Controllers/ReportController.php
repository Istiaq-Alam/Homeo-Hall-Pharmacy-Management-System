<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $range = $reports->range($request->input('from', now()->timezone(config('app.timezone'))->toDateString()), $request->input('to', now()->timezone(config('app.timezone'))->toDateString()));
        $summary = $reports->summary($range['from'], $range['to']);
        $salesByMedicine = $reports->medicineSales($range['from'], $range['to']);
        $inventory = $reports->inventory();
        $categoryExpenses = Expense::query()->select('expense_category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as transaction_count'))
            ->with('category')->where('status', 'completed')->whereBetween('expense_date', [$range['from'], $range['to']])->groupBy('expense_category_id')->get();
        $movements = $reports->movements($range['from'], $range['to'])->take(50);
        return view('reports.index', compact('range', 'summary', 'salesByMedicine', 'inventory', 'categoryExpenses', 'movements'));
    }

    public function monthly(Request $request, ReportService $reports): View
    {
        $month = CarbonImmutable::parse($request->input('month', now()->timezone(config('app.timezone'))->format('Y-m')));
        $range = ['from' => $month->startOfMonth()->toDateString(), 'to' => $month->endOfMonth()->toDateString()];
        return $this->indexForRange($range, $reports);
    }

    public function yearly(Request $request, ReportService $reports): View
    {
        $year = (int) $request->input('year', now()->timezone(config('app.timezone'))->year);
        abort_unless($year >= 2000 && $year <= 2100, 422);
        $months = collect(range(1, 12))->map(function (int $month) use ($year, $reports): array {
            $date = CarbonImmutable::create($year, $month, 1);
            return ['label' => $date->format('M'), ...$reports->summary($date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString())];
        });
        return view('reports.yearly', compact('year', 'months'));
    }

    public function inventory(ReportService $reports): View
    {
        return view('reports.inventory', ['inventory' => $reports->inventory()]);
    }

    public function medicineSales(Request $request, ReportService $reports): View
    {
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));
        return view('reports.medicine-sales', ['range' => $range, 'rows' => $reports->medicineSales($range['from'], $range['to'])]);
    }

    public function expenses(Request $request): View
    {
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $categories = ExpenseCategory::query()->withCount(['expenses as transaction_count' => fn ($query) => $query->where('status', 'completed')->whereBetween('expense_date', [$from, $to])])
            ->withSum(['expenses as total_amount' => fn ($query) => $query->where('status', 'completed')->whereBetween('expense_date', [$from, $to])], 'amount')->orderBy('name')->get();
        return view('reports.expenses', compact('from', 'to', 'categories'));
    }

    public function stockMovements(Request $request, ReportService $reports): View
    {
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));
        return view('reports.stock-movements', ['range' => $range, 'movements' => $reports->movements($range['from'], $range['to'])->paginate(30)]);
    }

    public function csv(Request $request, ReportService $reports): StreamedResponse
    {
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));
        $summary = $reports->summary($range['from'], $range['to']);
        return response()->streamDownload(function () use ($summary): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Metric', 'Value']);
            foreach (['sales' => 'Sales Revenue', 'purchases' => 'Purchases', 'cogs' => 'COGS', 'gross_profit' => 'Gross Profit', 'expenses' => 'Other Expenses', 'net_profit' => 'Net Operating Profit', 'units_sold' => 'Units Sold', 'units_purchased' => 'Units Purchased'] as $key => $label) {
                fputcsv($output, [$label, $summary[$key]]);
            }
            fclose($output);
        }, 'homeo-hall-report-'.$range['from'].'-'.$range['to'].'.csv', ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request, ReportService $reports): \Illuminate\Http\Response
    {
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));
        $summary = $reports->summary($range['from'], $range['to']);
        $pdf = app('dompdf.wrapper')->loadView('reports.pdf', compact('range', 'summary'));
        return $pdf->download('homeo-hall-report-'.$range['from'].'-'.$range['to'].'.pdf');
    }

    /** @param array{from: string, to: string} $range */
    private function indexForRange(array $range, ReportService $reports): View
    {
        $summary = $reports->summary($range['from'], $range['to']);
        $salesByMedicine = $reports->medicineSales($range['from'], $range['to']);
        $inventory = $reports->inventory();
        $categoryExpenses = Expense::query()->select('expense_category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as transaction_count'))
            ->with('category')->where('status', 'completed')->whereBetween('expense_date', [$range['from'], $range['to']])->groupBy('expense_category_id')->get();
        $movements = collect();
        return view('reports.index', compact('range', 'summary', 'salesByMedicine', 'inventory', 'categoryExpenses', 'movements'));
    }
}
