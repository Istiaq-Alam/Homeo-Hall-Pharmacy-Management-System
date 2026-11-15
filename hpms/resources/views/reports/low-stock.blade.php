@extends('layouts.app')
@section('title', 'Low-stock report')
@section('content')
<x-page-heading title="Low-stock report" description="Medicines at or below their configured minimum stock, including out-of-stock items." />
@include('reports.partials.tabs')
<div class="report-actions no-print" style="margin-bottom:18px"><button class="button button-muted button-small" type="button" onclick="window.print()"><x-icon name="printer" /> Print</button></div>
@if($inventory->isEmpty())<div class="card empty-state"><strong>Stock levels are healthy</strong>No medicine is at or below its minimum stock.</div>@else<div class="table-wrap"><table class="table"><thead><tr><th>Medicine</th><th>Potency</th><th>Category</th><th class="numeric">Current quantity</th><th class="numeric">Minimum stock</th><th>Status</th><th></th></tr></thead><tbody>@foreach($inventory as $medicine)<tr><td><strong>{{ $medicine->name }}</strong></td><td>{{ $medicine->potency ?: '—' }}</td><td>{{ $medicine->category->name }}</td><td class="numeric">{{ number_format($medicine->stock_quantity) }} {{ $medicine->unit }}</td><td class="numeric">{{ number_format($medicine->minimum_stock) }}</td><td>@include('medicines.partials.status', ['medicine' => $medicine])</td><td><a href="{{ route('medicines.adjustments.create') }}">Adjust</a></td></tr>@endforeach</tbody></table></div>@endif
@endsection
