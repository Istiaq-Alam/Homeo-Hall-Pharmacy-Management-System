<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Services\ReportService;
use App\Support\SimpleXlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $this->validateDates($request);
        $defaultDays = max(1, (int) (Setting::query()->where('key', 'report_default_days')->value('value') ?: 30));
        $range = $reports->range($request->input('from', now()->subDays($defaultDays - 1)->toDateString()), $request->input('to', now()->toDateString()));
        $summary = $reports->summary($range['from'], $range['to']);
        $salesByMedicine = $reports->medicineSales($range['from'], $range['to']);
        $inventory = $reports->inventory();
        $categoryExpenses = Expense::query()->select('expense_category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as transaction_count'))
            ->with('category')->where('status', 'completed')->whereBetween('expense_date', [$range['from'].' 00:00:00', $range['to'].' 23:59:59'])->groupBy('expense_category_id')->get();
        $movements = $reports->movements($range['from'], $range['to'])->take(50)->get();

        return view('reports.index', compact('range', 'summary', 'salesByMedicine', 'inventory', 'categoryExpenses', 'movements'));
    }

    public function monthly(Request $request, ReportService $reports): View
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = CarbonImmutable::parse($request->input('month', now()->timezone(config('app.timezone'))->format('Y-m')))->startOfMonth();
        $range = ['from' => $month->toDateString(), 'to' => $month->endOfMonth()->toDateString()];
        $summary = $reports->summary($range['from'], $range['to']);
        $comparison = collect(range(5, 0))->map(function (int $offset) use ($month, $reports): array {
            $period = $month->subMonths($offset);
            $data = $reports->summary($period->toDateString(), $period->endOfMonth()->toDateString());

            return ['label' => $period->format('M Y'), 'sales' => $data['sales'], 'purchases' => $data['purchases'], 'expenses' => $data['expenses'], 'cogs' => $data['cogs'], 'profit' => $data['net_profit']];
        });

        return view('reports.monthly', compact('range', 'month', 'summary', 'comparison'));
    }

    public function yearly(Request $request, ReportService $reports): View
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) $request->input('year', now()->timezone(config('app.timezone'))->year);
        $months = collect(range(1, 12))->map(function (int $month) use ($year, $reports): array {
            $date = CarbonImmutable::create($year, $month, 1);

            return ['label' => $date->format('M'), ...$reports->summary($date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString())];
        });

        return view('reports.yearly', compact('year', 'months'));
    }

    public function daily(Request $request, ReportService $reports): View
    {
        $request->validate(['date' => ['nullable', 'date']]);
        $date = $request->input('date', now()->timezone(config('app.timezone'))->toDateString());

        return $this->indexForRange(['from' => $date, 'to' => $date], $reports);
    }

    public function inventory(ReportService $reports): View
    {
        return view('reports.inventory', ['inventory' => $reports->inventory()]);
    }

    public function lowStock(ReportService $reports): View
    {
        return view('reports.low-stock', ['inventory' => $reports->inventory()->filter(fn ($medicine) => $medicine->stock_quantity <= $medicine->minimum_stock)->values()]);
    }

    public function medicineSales(Request $request, ReportService $reports): View
    {
        $this->validateDates($request);
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));

        return view('reports.medicine-sales', ['range' => $range, 'rows' => $reports->medicineSales($range['from'], $range['to'])]);
    }

    public function expenses(Request $request, ReportService $reports): View
    {
        $this->validateDates($request);
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));
        $from = $range['from'];
        $to = $range['to'];
        $categories = ExpenseCategory::query()->withCount(['expenses as transaction_count' => fn ($query) => $query->where('status', 'completed')->whereBetween('expense_date', [$from.' 00:00:00', $to.' 23:59:59'])])
            ->withSum(['expenses as total_amount' => fn ($query) => $query->where('status', 'completed')->whereBetween('expense_date', [$from.' 00:00:00', $to.' 23:59:59'])], 'amount')->orderBy('name')->get();

        return view('reports.expenses', compact('from', 'to', 'categories'));
    }

    public function stockMovements(Request $request, ReportService $reports): View
    {
        $this->validateDates($request);
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));

        return view('reports.stock-movements', ['range' => $range, 'movements' => $reports->movements($range['from'], $range['to'])->paginate(30)]);
    }

    public function csv(Request $request, ReportService $reports): StreamedResponse
    {
        $this->validateDates($request);
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

    public function xlsx(Request $request, ReportService $reports): BinaryFileResponse
    {
        $this->validateDates($request);
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));
        $summary = $reports->summary($range['from'], $range['to']);
        $rows = [['Homeo-Hall financial report'], ['Date range', $range['from'].' to '.$range['to']], [], ['Metric', 'Value']];
        foreach (['sales' => 'Sales revenue', 'purchases' => 'Purchases', 'cogs' => 'COGS', 'gross_profit' => 'Gross profit', 'expenses' => 'Other operating expenses', 'net_profit' => 'Net operating profit', 'units_sold' => 'Units sold', 'units_purchased' => 'Units purchased', 'inventory_value' => 'Closing inventory value'] as $key => $label) {
            $rows[] = [$label, (float) $summary[$key]];
        }
        $path = app(SimpleXlsxWriter::class)->download($rows, 'report.xlsx');

        return response()->download($path, 'homeo-hall-report-'.$range['from'].'-'.$range['to'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, ReportService $reports): Response
    {
        $this->validateDates($request);
        $range = $reports->range($request->input('from', now()->toDateString()), $request->input('to', now()->toDateString()));
        $summary = $reports->summary($range['from'], $range['to']);
        $pdf = app('dompdf.wrapper')->loadView('reports.pdf', compact('range', 'summary'));

        return $pdf->download('homeo-hall-report-'.$range['from'].'-'.$range['to'].'.pdf');
    }

    private function validateDates(Request $request): void
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
    }

    /** @param array{from: string, to: string} $range */
    private function indexForRange(array $range, ReportService $reports): View
    {
        $summary = $reports->summary($range['from'], $range['to']);
        $salesByMedicine = $reports->medicineSales($range['from'], $range['to']);
        $inventory = $reports->inventory();
        $categoryExpenses = Expense::query()->select('expense_category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as transaction_count'))
            ->with('category')->where('status', 'completed')->whereBetween('expense_date', [$range['from'].' 00:00:00', $range['to'].' 23:59:59'])->groupBy('expense_category_id')->get();
        $movements = collect();

        return view('reports.index', compact('range', 'summary', 'salesByMedicine', 'inventory', 'categoryExpenses', 'movements'));
    }
}
