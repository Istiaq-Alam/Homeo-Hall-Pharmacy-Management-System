<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Sale;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::query()->withCount('items')
            ->when($request->filled('q'), fn ($query) => $query->where('sale_number', 'like', '%'.$request->q.'%'))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('sale_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('sale_date', '<=', $request->to))
            ->when($request->filled('medicine'), fn ($query) => $query->whereHas('items', fn ($items) => $items->where('medicine_id', $request->integer('medicine'))))
            ->latest('sale_date')->paginate(15)->withQueryString();

        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        return view('sales.form', ['medicines' => Medicine::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, TransactionService $transactions): RedirectResponse
    {
        $data = $request->validate([
            'sale_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.medicine_id' => ['required', 'distinct', 'exists:medicines,id'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'items.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ]);
        $sale = $transactions->recordSale($data, (int) $request->user()->id);

        return redirect()->route('sales.show', $sale)->with('success', 'Sale recorded and stock updated.');
    }

    public function show(Sale $sale): View
    {
        return view('sales.show', ['sale' => $sale->load('items.medicine')]);
    }

    public function cancel(Request $request, Sale $sale, TransactionService $transactions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $transactions->cancelSale($sale, $data['reason']);

        return back()->with('success', 'Sale cancelled and stock restored.');
    }

    public function price(Request $request): array
    {
        $data = $request->validate(['medicine_id' => ['required', 'exists:medicines,id']]);
        $medicine = Medicine::query()->findOrFail($data['medicine_id']);

        return ['selling_price' => $medicine->selling_price, 'stock' => $medicine->stock_quantity, 'name' => $medicine->name.' '.$medicine->potency];
    }
}
