@extends('layouts.app')
@section('title', 'Expense report')
@section('content')
<x-page-heading title="Expense report" description="Operating expense totals by category; medicine purchases are excluded." />
@include('reports.partials.tabs')
<form class="filters no-print" method="GET" action="{{ route('reports.expenses') }}"><label>From<input name="from" type="date" value="{{ $from }}" required></label><label>To<input name="to" type="date" value="{{ $to }}" required></label><button class="button button-muted" type="submit">Generate</button><button class="button button-muted" type="button" onclick="window.print()"><x-icon name="printer" /> Print</button></form>
<div class="print-only"><h2>Homeo-Hall expense report</h2><p>{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</p></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Expense category</th><th class="numeric">Transactions</th><th class="numeric">Total amount</th></tr></thead><tbody>@forelse($categories as $category)<tr><td><strong>{{ $category->name }}</strong></td><td class="numeric">{{ $category->transaction_count }}</td><td class="numeric">৳{{ number_format((float) ($category->total_amount ?? 0), 2) }}</td></tr>@empty<tr><td colspan="3" class="empty-state">No expense categories are configured.</td></tr>@endforelse</tbody></table></div>
@endsection
