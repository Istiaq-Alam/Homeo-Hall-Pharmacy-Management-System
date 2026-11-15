@extends('layouts.app')
@section('title', __('expenses.details'))
@section('content')
<div class="breadcrumb"><a href="{{ route('expenses.index') }}">{{ __('expenses.title') }}</a> / {{ __('common.details') }}</div>
<x-page-heading :title="$expense->description" :description="default_label($expense->category->name, 'expense_categories').' · '.localized_date($expense->expense_date)">
    <x-slot:action>@if($expense->status === 'completed')<a class="button button-secondary" href="{{ route('expenses.edit', $expense) }}">{{ __('common.edit') }}</a>@endif<span class="status {{ $expense->status === 'completed' ? 'status-completed' : 'status-cancelled' }}">{{ status_label($expense->status) }}</span></x-slot:action>
</x-page-heading>
<div class="stat-grid"><x-stat-card :label="__('expenses.amount')" :value="money($expense->amount)" /><x-stat-card :label="__('expenses.category')" :value="default_label($expense->category->name, 'expense_categories')" /><x-stat-card :label="__('expenses.date')" :value="localized_date($expense->expense_date)" /><x-stat-card :label="__('common.recorded')" :value="localized_date($expense->created_at)" /></div>
@if($expense->notes)<div class="card" style="margin-bottom:18px"><div class="section-title"><h2>{{ __('expenses.notes_heading') }}</h2></div><p>{{ $expense->notes }}</p></div>@endif
@if($expense->status === 'completed')<div class="card"><div class="section-title"><h2>{{ __('expenses.cancel_title') }}</h2></div><p style="color:#7a7a7a;font-size:14px">{{ __('expenses.cancel_copy') }}</p><form method="POST" action="{{ route('expenses.cancel', $expense) }}" class="form-stack" onsubmit="return confirmAction(@js(__('expenses.cancel_confirm')))">@csrf<label>{{ __('expenses.cancellation_reason') }}<input name="reason" required maxlength="1000"></label><div><button class="button button-danger" type="submit">{{ __('expenses.cancel_expense') }}</button></div></form></div>@else<div class="notice notice-error">{{ __('expenses.cancelled_on', ['date' => localized_date_time($expense->cancelled_at), 'reason' => $expense->cancellation_reason]) }}</div>@endif
@endsection
