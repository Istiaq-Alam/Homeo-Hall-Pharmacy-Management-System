@extends('layouts.app')
@section('title', $expense->exists ? 'Edit expense' : 'Add expense')
@section('content')
<div class="breadcrumb"><a href="{{ route('expenses.index') }}">Expenses</a> / {{ $expense->exists ? 'Edit' : 'Add' }}</div>
<x-page-heading :title="$expense->exists ? 'Edit expense' : 'Add expense'" description="Use this for chamber operating costs, not medicine stock purchases." />
<div class="card form-card"><form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="form-stack">@csrf @if($expense->exists) @method('PUT') @endif
<div class="form-grid"><label>Expense date<input name="expense_date" type="date" value="{{ old('expense_date', $expense->expense_date?->toDateString() ?? now()->toDateString()) }}" required></label><label>Amount (৳)<input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $expense->amount) }}" required></label><label>Category<select name="expense_category_id" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label><label>Description<input name="description" value="{{ old('description', $expense->description) }}" maxlength="200" required placeholder="e.g. Chamber electricity bill"></label></div>
<label>Notes<textarea name="notes" maxlength="2000">{{ old('notes', $expense->notes) }}</textarea></label>
<div class="form-actions"><button class="button button-primary" type="submit">{{ $expense->exists ? 'Save changes' : 'Add expense' }}</button><a class="button button-muted" href="{{ route('expenses.index') }}">Cancel</a></div>
</form></div>
@endsection
