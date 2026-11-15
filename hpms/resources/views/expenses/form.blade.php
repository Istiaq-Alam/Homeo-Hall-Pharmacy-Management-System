@extends('layouts.app')
@section('title', $expense->exists ? __('expenses.edit') : __('expenses.add'))
@section('content')
<div class="breadcrumb"><a href="{{ route('expenses.index') }}">{{ __('expenses.title') }}</a> / {{ $expense->exists ? __('common.edit') : __('common.add') }}</div>
<x-page-heading :title="$expense->exists ? __('expenses.edit') : __('expenses.add')" :description="__('expenses.edit_description')" />
<div class="card form-card"><form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="form-stack">@csrf @if($expense->exists) @method('PUT') @endif
<div class="form-grid"><label>{{ __('expenses.date') }}<input name="expense_date" type="date" value="{{ old('expense_date', $expense->expense_date?->toDateString() ?? now()->toDateString()) }}" required></label><label>{{ __('expenses.amount') }} (৳)<input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $expense->amount) }}" required></label><label>{{ __('expenses.category') }}<select name="expense_category_id" required><option value="">{{ __('common.select_category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>{{ default_label($category->name, 'expense_categories') }}</option>@endforeach</select></label><label>{{ __('expenses.description_label') }}<input name="description" value="{{ old('description', $expense->description) }}" maxlength="200" required placeholder="{{ __('expenses.description_placeholder') }}"></label></div>
<label>{{ __('expenses.notes') }}<textarea name="notes" maxlength="2000">{{ old('notes', $expense->notes) }}</textarea></label>
<div class="form-actions"><button class="button button-primary" type="submit">{{ $expense->exists ? __('common.save_changes') : __('expenses.add') }}</button><a class="button button-muted" href="{{ route('expenses.index') }}">{{ __('common.cancel') }}</a></div>
</form></div>
@endsection
