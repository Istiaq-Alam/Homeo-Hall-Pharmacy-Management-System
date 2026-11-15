@extends('layouts.app')
@section('title', 'Sales')
@section('content')
<x-page-heading title="Sales" description="Manual sales records; no customer information is collected.">
    <x-slot:action><a class="button button-primary" href="{{ route('sales.create') }}"><x-icon name="plus" /> Record sale</a></x-slot:action>
</x-page-heading>
<form class="filters" method="GET">
<label>Sale number<input name="q" value="{{ request('q') }}" placeholder="e.g. SAL-2026"></label><label>Medicine<select name="medicine"><option value="">All medicines</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}" @selected(request('medicine') == $medicine->id)>{{ $medicine->name }} {{ $medicine->potency }}</option>@endforeach</select></label><label>From<input name="from" type="date" value="{{ request('from') }}"></label><label>To<input name="to" type="date" value="{{ request('to') }}"></label><button class="button button-muted" type="submit">Apply filters</button></form>
@if($sales->isEmpty())<div class="card empty-state"><strong>No sales found</strong>Record a sale after medicines have stock.</div>@else<div class="table-wrap"><table class="table"><thead><tr><th>Sale</th><th>Date</th><th>Items</th><th class="numeric">Total</th><th>Status</th></tr></thead><tbody>@foreach($sales as $sale)<tr><td><a href="{{ route('sales.show', $sale) }}"><strong>{{ $sale->sale_number }}</strong></a></td><td>{{ $sale->sale_date->format('d M Y') }}</td><td>{{ $sale->items_count }}</td><td class="numeric">৳{{ number_format((float) $sale->total_amount, 2) }}</td><td><span class="status {{ $sale->status === 'completed' ? 'status-completed' : 'status-cancelled' }}">{{ ucfirst($sale->status) }}</span></td></tr>@endforeach</tbody></table></div><div class="pagination"><span>Showing {{ $sales->firstItem() }}–{{ $sales->lastItem() }} of {{ $sales->total() }}</span>{{ $sales->links() }}</div>@endif
@endsection
