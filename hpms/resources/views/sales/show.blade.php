@extends('layouts.app')
@section('title', __('sales.details'))
@section('content')
<div class="breadcrumb"><a href="{{ route('sales.index') }}">{{ __('sales.title') }}</a> / {{ $sale->sale_number }}</div>
<x-page-heading :title="$sale->sale_number" :description="__('sales.recorded_on', ['date' => localized_date($sale->sale_date)])">
    <x-slot:action>
        <div class="heading-action">
            @if($sale->status === 'completed')<span class="status {{ $sale->payment_status === 'unpaid' ? 'status-unpaid' : 'status-paid' }}">{{ $sale->payment_status === 'unpaid' ? __('sales.unpaid') : __('sales.paid') }}</span>@endif
            <span class="status {{ $sale->status === 'completed' ? 'status-completed' : 'status-cancelled' }}">{{ status_label($sale->status) }}</span>
        </div>
    </x-slot:action>
</x-page-heading>
@php($cogs = $sale->items->sum('line_cost_total'))
<div class="stat-grid"><x-stat-card :label="__('sales.sale_total_card')" :value="money($sale->total_amount)" /><x-stat-card :label="__('sales.historical_cogs')" :value="money($cogs)" :note="__('sales.stored_at_sale')" /><x-stat-card :label="__('sales.gross_profit')" :value="money((float) $sale->total_amount - (float) $cogs)" /><x-stat-card :label="__('sales.units_sold')" :value="localized_number($sale->items->sum('quantity'))" /><x-stat-card :label="__('sales.payment_summary')" :value="$sale->payment_status === 'unpaid' ? __('sales.unpaid') : __('sales.paid')" :note="$sale->paid_at ? __('sales.paid_on', ['date' => localized_date($sale->paid_at)]) : __('sales.not_paid_yet')" /></div>
<div class="card" style="margin-bottom:18px"><div class="section-title"><h2>{{ __('sales.sale_items') }}</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>{{ __('sales.medicine') }}</th><th class="numeric">{{ __('sales.quantity') }}</th><th class="numeric">{{ __('sales.unit_selling_price') }}</th><th class="numeric">{{ __('sales.unit_cost_historical') }}</th><th class="numeric">{{ __('sales.line_total') }}</th><th class="numeric">{{ __('sales.line_cogs') }}</th></tr></thead><tbody>@foreach($sale->items as $item)<tr><td><a href="{{ route('medicines.show', $item->medicine) }}">{{ $item->medicine->name }} {{ $item->medicine->potency }}</a></td><td class="numeric">{{ localized_number($item->quantity) }}</td><td class="numeric">{{ money($item->unit_selling_price) }}</td><td class="numeric">{{ money($item->unit_cost_price, 4) }}</td><td class="numeric">{{ money($item->line_total) }}</td><td class="numeric">{{ money($item->line_cost_total) }}</td></tr>@endforeach</tbody></table></div>@if($sale->notes)<p style="margin-top:16px"><strong>{{ __('sales.notes_label') }}</strong> {{ $sale->notes }}</p>@endif</div>
<div class="card" style="margin-bottom:18px"><div class="section-title"><h2>{{ __('sales.payment_summary') }}</h2><span class="status {{ $sale->payment_status === 'unpaid' ? 'status-unpaid' : 'status-paid' }}">{{ $sale->payment_status === 'unpaid' ? __('sales.unpaid') : __('sales.paid') }}</span></div>
@if($sale->isUnpaid())
<p style="color:#7a7a7a;font-size:14px">{{ __('sales.mark_paid_copy') }}</p>
<form method="POST" action="{{ route('sales.mark-paid', $sale) }}" onsubmit="return confirmAction(@js(__('sales.mark_paid_confirm')))">@csrf<button class="button button-primary" type="submit"><x-icon name="check" /> {{ __('sales.mark_paid') }}</button></form>
@elseif($sale->status === 'completed')
<p style="color:#7a7a7a;font-size:14px">{{ $sale->paid_at ? __('sales.paid_on', ['date' => localized_date_time($sale->paid_at)]) : __('sales.payment_note') }}</p>
@else
<p style="color:#7a7a7a;font-size:14px">{{ __('sales.cancelled_on', ['date' => localized_date_time($sale->cancelled_at), 'reason' => $sale->cancellation_reason]) }}</p>
@endif
</div>
@if($sale->status === 'completed')<div class="card"><div class="section-title"><h2>{{ __('sales.cancel_title') }}</h2></div><p style="color:#7a7a7a;font-size:14px">{{ __('sales.cancel_copy') }}</p><form method="POST" action="{{ route('sales.cancel', $sale) }}" class="form-stack" onsubmit="return confirmAction(@js(__('sales.cancel_confirm')))">@csrf<label>{{ __('sales.cancellation_reason') }}<input name="reason" required maxlength="1000" placeholder="{{ __('sales.cancellation_placeholder') }}"></label><div><button class="button button-danger" type="submit">{{ __('sales.cancel_sale') }}</button></div></form></div>@else<div class="notice notice-error">{{ __('sales.cancelled_on', ['date' => localized_date_time($sale->cancelled_at), 'reason' => $sale->cancellation_reason]) }}</div>@endif
@endsection
