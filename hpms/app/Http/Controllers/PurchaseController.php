<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Purchase;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $purchases = Purchase::query()->withCount('items')
            ->when($request->filled('q'), fn ($query) => $query->where('purchase_number', 'like', '%'.$request->q.'%'))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('purchase_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('purchase_date', '<=', $request->to))
            ->when($request->filled('medicine'), fn ($query) => $query->whereHas('items', fn ($items) => $items->where('medicine_id', $request->integer('medicine'))))
            ->latest('purchase_date')->paginate(15)->withQueryString();

        return view('purchases.index', ['purchases' => $purchases, 'medicines' => Medicine::query()->orderBy('name')->get(['id', 'name', 'potency'])]);
    }

    public function create(): View
    {
        return view('purchases.form', ['medicines' => Medicine::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, TransactionService $transactions): RedirectResponse
    {
        $data = $request->validate([
            'purchase_date' => ['required', 'date'], 'supplier_name' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.medicine_id' => ['required', 'distinct', 'exists:medicines,id'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'items.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ]);
        $purchase = $transactions->recordPurchase($data, (int) $request->user()->id);

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase recorded and stock updated.');
    }

    public function show(Purchase $purchase): View
    {
        return view('purchases.show', ['purchase' => $purchase->load('items.medicine')]);
    }

    public function cancel(Request $request, Purchase $purchase, TransactionService $transactions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $transactions->cancelPurchase($purchase, $data['reason']);

        return back()->with('success', 'Purchase cancelled and stock reversed.');
    }
}
