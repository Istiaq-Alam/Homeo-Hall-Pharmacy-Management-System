<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function index(Request $request): View
    {
        $medicines = Medicine::query()->with('category')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('name', 'like', '%'.$request->q.'%')->orWhere('potency', 'like', '%'.$request->q.'%')))
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->when($request->status === 'low', fn ($query) => $query->whereColumn('stock_quantity', '<=', 'minimum_stock')->where('stock_quantity', '>', 0))
            ->when($request->status === 'out', fn ($query) => $query->where('stock_quantity', 0))
            ->when($request->filled('form'), fn ($query) => $query->where('form', $request->form))
            ->when($request->get('sort') === 'stock', fn ($query) => $query->orderBy('stock_quantity'), fn ($query) => $query->orderBy('name'))
            ->paginate(15)->withQueryString();

        return view('medicines.index', ['medicines' => $medicines, 'categories' => MedicineCategory::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('medicines.form', ['medicine' => new Medicine(), 'categories' => MedicineCategory::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $medicine = Medicine::create($this->validated($request));

        return redirect()->route('medicines.show', $medicine)->with('success', 'Medicine added successfully.');
    }

    public function show(Medicine $medicine): View
    {
        return view('medicines.show', ['medicine' => $medicine->load('category'), 'movements' => $medicine->movements()->latest('movement_date')->paginate(10)]);
    }

    public function edit(Medicine $medicine): View
    {
        return view('medicines.form', ['medicine' => $medicine, 'categories' => MedicineCategory::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, Medicine $medicine): RedirectResponse
    {
        $medicine->update($this->validated($request, $medicine));

        return redirect()->route('medicines.show', $medicine)->with('success', 'Medicine updated successfully.');
    }

    public function deactivate(Medicine $medicine): RedirectResponse
    {
        $medicine->update(['is_active' => false]);

        return redirect()->route('medicines.index')->with('success', 'Medicine deactivated.');
    }

    public function categories(): View
    {
        return view('medicines.categories', ['categories' => MedicineCategory::query()->withCount('medicines')->orderBy('name')->get()]);
    }

    public function saveCategory(Request $request, ?MedicineCategory $category = null): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('medicine_categories', 'name')->ignore($category?->id)], 'is_active' => ['sometimes', 'boolean']]);
        ($category ?? new MedicineCategory())->fill($data)->save();

        return back()->with('success', 'Medicine category saved.');
    }

    public function adjustmentForm(): View
    {
        return view('medicines.adjustment', ['medicines' => Medicine::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function adjust(Request $request, TransactionService $transactions): RedirectResponse
    {
        $data = $request->validate(['medicine_id' => ['required', 'exists:medicines,id'], 'adjustment_type' => ['required', Rule::in(['increase', 'decrease'])], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'reason' => ['required', 'string', 'max:255'], 'adjustment_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $transactions->adjustStock($data, (int) $request->user()->id);

        return redirect()->route('medicines.index')->with('success', 'Stock adjustment recorded.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Medicine $medicine = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:medicine_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'potency' => ['nullable', 'string', 'max:50'],
            'form' => ['required', 'string', 'max:50'],
            'unit' => ['required', 'string', 'max:50'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['potency'] = trim((string) ($data['potency'] ?? ''));

        return $data;
    }
}
