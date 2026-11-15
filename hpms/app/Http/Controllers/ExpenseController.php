<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = Expense::query()->with('category')
            ->when($request->filled('q'), fn ($query) => $query->where('description', 'like', '%'.$request->q.'%'))
            ->when($request->filled('category'), fn ($query) => $query->where('expense_category_id', $request->integer('category')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('expense_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('expense_date', '<=', $request->to))
            ->latest('expense_date')->paginate(15)->withQueryString();

        return view('expenses.index', ['expenses' => $expenses, 'categories' => ExpenseCategory::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('expenses.form', ['expense' => new Expense(), 'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, TransactionService $transactions): RedirectResponse
    {
        $expense = $transactions->recordExpense($this->validated($request), (int) $request->user()->id);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function show(Expense $expense): View
    {
        return view('expenses.show', ['expense' => $expense->load('category')]);
    }

    public function edit(Expense $expense): View
    {
        return view('expenses.form', ['expense' => $expense, 'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        if ($expense->status !== 'completed') {
            abort(422, 'Cancelled expenses cannot be edited.');
        }
        $expense->update($this->validated($request));

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense updated.');
    }

    public function cancel(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        if ($expense->status !== 'completed') {
            return back()->withErrors(['reason' => 'This expense is already cancelled.']);
        }
        $expense->update(['status' => 'cancelled', 'cancellation_reason' => $data['reason'], 'cancelled_at' => now()]);

        return back()->with('success', 'Expense cancelled.');
    }

    public function categories(): View
    {
        return view('expenses.categories', ['categories' => ExpenseCategory::query()->withCount('expenses')->orderBy('name')->get()]);
    }

    public function saveCategory(Request $request, ?ExpenseCategory $category = null): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($category?->id)], 'is_active' => ['sometimes', 'boolean']]);
        ($category ?? new ExpenseCategory())->fill($data)->save();

        return back()->with('success', 'Expense category saved.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
