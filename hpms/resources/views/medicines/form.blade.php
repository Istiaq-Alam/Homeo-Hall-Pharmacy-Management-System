@extends('layouts.app')
@section('title', $medicine->exists ? 'Edit medicine' : 'Add medicine')
@section('content')
<div class="breadcrumb"><a href="{{ route('medicines.index') }}">Medicines</a> / {{ $medicine->exists ? 'Edit' : 'Add' }}</div>
<x-page-heading :title="$medicine->exists ? 'Edit medicine' : 'Add medicine'" description="Use a separate record for each potency. Stock changes are made through purchases, sales, or stock adjustments." />
<div class="card form-card"><form method="POST" action="{{ $medicine->exists ? route('medicines.update', $medicine) : route('medicines.store') }}" class="form-stack">@csrf @if($medicine->exists) @method('PUT') @endif
<div class="form-grid">
<label>Medicine name<input name="name" value="{{ old('name', $medicine->name) }}" required maxlength="150"></label>
<label>Potency<input name="potency" value="{{ old('potency', $medicine->potency) }}" placeholder="e.g. 30C, 200C, 1M"></label>
<label>Category<select name="category_id" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $medicine->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label>Medicine form<select name="form" required>@foreach(['Liquid','Globules','Tablets','Powder','Ointment','Other'] as $form)<option @selected(old('form', $medicine->form) === $form)>{{ $form }}</option>@endforeach</select></label>
<label>Unit<select name="unit" required>@foreach(['Bottle','Packet','Vial','Tube','Box','Piece'] as $unit)<option @selected(old('unit', $medicine->unit) === $unit)>{{ $unit }}</option>@endforeach</select></label>
<label>Minimum stock<input name="minimum_stock" type="number" min="0" step="1" value="{{ old('minimum_stock', $medicine->minimum_stock ?? ($appSettings['low_stock_default'] ?? 5)) }}" required></label>
<label>Purchase price (৳)<input name="purchase_price" type="number" min="0" step="0.01" value="{{ old('purchase_price', $medicine->purchase_price ?? 0) }}" required></label>
<label>Selling price (৳)<input name="selling_price" type="number" min="0" step="0.01" value="{{ old('selling_price', $medicine->selling_price ?? 0) }}" required></label>
</div>
<label>Description<textarea name="description" maxlength="4000" placeholder="Optional notes about this medicine">{{ old('description', $medicine->description) }}</textarea></label>
@if($medicine->exists)<p class="report-note">Current stock: <strong>{{ $medicine->stock_quantity }}</strong> {{ $medicine->unit }}. To correct stock, use <a href="{{ route('medicines.adjustments.create') }}">Stock adjustment</a> so the ledger stays accurate.</p>@endif
<div class="form-actions"><button class="button button-primary" type="submit">{{ $medicine->exists ? 'Save changes' : 'Add medicine' }}</button><a class="button button-muted" href="{{ route('medicines.index') }}">Cancel</a></div>
</form></div>
@endsection
