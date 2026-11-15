@extends('layouts.app')
@section('title', __('reports.low_stock_title'))
@section('content')
<x-page-heading :title="__('reports.low_stock_title')" :description="__('reports.low_stock_description')" />
@include('reports.partials.tabs')
<div class="report-actions no-print" style="margin-bottom:18px"><button class="button button-muted button-small" type="button" onclick="window.print()"><x-icon name="printer" /> {{ __('common.print') }}</button></div>
@if($inventory->isEmpty())<div class="card empty-state"><strong>{{ __('reports.healthy_title') }}</strong>{{ __('reports.healthy_copy') }}</div>@else<div class="table-wrap"><table class="table"><thead><tr><th>{{ __('reports.medicine') }}</th><th>{{ __('reports.potency') }}</th><th>{{ __('common.category') }}</th><th class="numeric">{{ __('reports.current_quantity') }}</th><th class="numeric">{{ __('reports.minimum_stock') }}</th><th>{{ __('common.status') }}</th><th></th></tr></thead><tbody>@foreach($inventory as $medicine)<tr><td><strong>{{ $medicine->name }}</strong></td><td>{{ $medicine->potency ?: __('common.none') }}</td><td>{{ default_label($medicine->category->name, 'medicine_categories') }}</td><td class="numeric">{{ localized_number($medicine->stock_quantity) }} {{ default_label($medicine->unit, 'units') }}</td><td class="numeric">{{ localized_number($medicine->minimum_stock) }}</td><td>@include('medicines.partials.status', ['medicine' => $medicine])</td><td><a href="{{ route('medicines.adjustments.create') }}">{{ __('reports.adjust') }}</a></td></tr>@endforeach</tbody></table></div>@endif
@endsection
