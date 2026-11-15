@extends('layouts.app')
@section('title', 'Stock adjustment')
@section('content')
<div class="breadcrumb"><a href="{{ route('medicines.index') }}">Medicines</a> / Stock adjustment</div>
<x-page-heading title="Stock adjustment" description="Use this only for physical count corrections, damage, or other stock changes outside purchases and sales. Every adjustment is recorded in the ledger." />
<div class="card form-card"><form method="POST" action="{{ route('medicines.adjustments.store') }}" class="form-stack">@csrf
<label>Medicine<select name="medicine_id" required><option value="">Select medicine</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}" @selected(old('medicine_id') == $medicine->id)>{{ $medicine->name }} {{ $medicine->potency }} ({{ $medicine->stock_quantity }} {{ $medicine->unit }})</option>@endforeach</select></label>
<div class="form-grid"><label>Adjustment<select name="adjustment_type" required><option value="increase" @selected(old('adjustment_type') === 'increase')>Increase stock</option><option value="decrease" @selected(old('adjustment_type') === 'decrease')>Decrease stock</option></select></label><label>Quantity<input name="quantity" type="number" min="1" max="1000000" value="{{ old('quantity', 1) }}" required></label><label>Adjustment date<input name="adjustment_date" type="date" value="{{ old('adjustment_date', now()->toDateString()) }}" required></label><label>Reason<input name="reason" value="{{ old('reason') }}" placeholder="e.g. Physical count correction" required maxlength="255"></label></div>
<label>Notes<textarea name="notes" maxlength="2000" placeholder="Optional details">{{ old('notes') }}</textarea></label>
<div class="form-actions"><button class="button button-primary" type="submit">Record adjustment</button><a class="button button-muted" href="{{ route('medicines.index') }}">Cancel</a></div>
</form></div>
@endsection
