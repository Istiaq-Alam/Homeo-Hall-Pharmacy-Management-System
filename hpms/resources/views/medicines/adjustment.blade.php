@extends('layouts.app')
@section('title', __('medicines.adjustment_title'))
@section('content')
<div class="breadcrumb"><a href="{{ route('medicines.index') }}">{{ __('medicines.title') }}</a> / {{ __('medicines.adjustment_title') }}</div>
<x-page-heading :title="__('medicines.adjustment_title')" :description="__('medicines.adjustment_description')" />
<div class="card form-card"><form method="POST" action="{{ route('medicines.adjustments.store') }}" class="form-stack">@csrf
<label>{{ __('medicines.title') }}<select name="medicine_id" required><option value="">{{ __('common.select_medicine') }}</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}" @selected(old('medicine_id') == $medicine->id)>{{ $medicine->name }} {{ $medicine->potency }} ({{ localized_number($medicine->stock_quantity) }} {{ default_label($medicine->unit, 'units') }})</option>@endforeach</select></label>
<div class="form-grid"><label>{{ __('medicines.adjustment_type') }}<select name="adjustment_type" required><option value="increase" @selected(old('adjustment_type') === 'increase')>{{ __('medicines.increase') }}</option><option value="decrease" @selected(old('adjustment_type') === 'decrease')>{{ __('medicines.decrease') }}</option></select></label><label>{{ __('common.quantity') }}<input name="quantity" type="number" min="1" max="1000000" value="{{ old('quantity', 1) }}" required></label><label>{{ __('medicines.adjustment_date') }}<input name="adjustment_date" type="date" value="{{ old('adjustment_date', now()->toDateString()) }}" required></label><label>{{ __('medicines.reason') }}<input name="reason" value="{{ old('reason') }}" placeholder="{{ __('medicines.reason_placeholder') }}" required maxlength="255"></label></div>
<label>{{ __('common.notes') }}<textarea name="notes" maxlength="2000" placeholder="{{ __('medicines.notes_placeholder') }}">{{ old('notes') }}</textarea></label>
<div class="form-actions"><button class="button button-primary" type="submit">{{ __('medicines.record_adjustment') }}</button><a class="button button-muted" href="{{ route('medicines.index') }}">{{ __('common.cancel') }}</a></div>
</form></div>
@endsection
