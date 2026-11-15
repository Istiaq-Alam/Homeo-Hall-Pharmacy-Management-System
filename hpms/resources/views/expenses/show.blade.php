@extends('layouts.app')
@section('title', 'Expense details')
@section('content')
<div class="breadcrumb"><a href="{{ route('expenses.index') }}">Expenses</a> / Details</div>
<x-page-heading :title="$expense->description" :description="$expense->category->name.' · '.$expense->expense_date->format('d M Y')">
    <x-slot:action>@if($expense->status === 'completed')<a class="button button-secondary" href="{{ route('expenses.edit', $expense) }}">Edit</a>@endif<span class="status {{ $expense->status === 'completed' ? 'status-completed' : 'status-cancelled' }}">{{ ucfirst($expense->status) }}</span></x-slot:action>
</x-page-heading>
<div class="stat-grid"><x-stat-card label="Amount" :value="'৳'.number_format((float) $expense->amount, 2)" /><x-stat-card label="Category" :value="$expense->category->name" /><x-stat-card label="Expense date" :value="$expense->expense_date->format('d M Y')" /><x-stat-card label="Recorded" :value="$expense->created_at->format('d M Y')" /></div>
@if($expense->notes)<div class="card" style="margin-bottom:18px"><div class="section-title"><h2>Notes</h2></div><p>{{ $expense->notes }}</p></div>@endif
@if($expense->status === 'completed')<div class="card"><div class="section-title"><h2>Cancel expense</h2></div><p style="color:#7a7a7a;font-size:14px">Cancelled expenses are preserved but excluded from normal expense and profit reports.</p><form method="POST" action="{{ route('expenses.cancel', $expense) }}" class="form-stack" onsubmit="return confirmAction('Cancel this expense?')">@csrf<label>Cancellation reason<input name="reason" required maxlength="1000"></label><div><button class="button button-danger" type="submit">Cancel expense</button></div></form></div>@else<div class="notice notice-error">Cancelled on {{ $expense->cancelled_at?->format('d M Y, h:i A') }}. Reason: {{ $expense->cancellation_reason }}</div>@endif
@endsection
