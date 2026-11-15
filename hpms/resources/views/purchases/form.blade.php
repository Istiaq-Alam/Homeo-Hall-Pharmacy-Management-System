@extends('layouts.app')
@section('title', 'Record purchase')
@section('content')
<div class="breadcrumb"><a href="{{ route('purchases.index') }}">Purchases</a> / Record</div>
<x-page-heading title="Record purchase" description="Add each medicine received. Saving this purchase increases stock atomically." />
@php($medicineData = $medicines->map(fn ($medicine) => ['id' => $medicine->id, 'name' => $medicine->name.' '.$medicine->potency.' ('.$medicine->form.' · '.$medicine->unit.')', 'price' => (float) $medicine->purchase_price, 'stock' => $medicine->stock_quantity, 'unit' => $medicine->unit]))
<script type="application/json" id="medicine-data">{!! json_encode($medicineData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="old-line-items">{!! old('items') ? json_encode(array_values(old('items')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '' !!}</script>
<div class="card"><form method="POST" action="{{ route('purchases.store') }}" class="form-stack">@csrf
<div class="form-grid"><label>Purchase date<input name="purchase_date" type="date" value="{{ old('purchase_date', now()->toDateString()) }}" required></label><label>Supplier (optional)<input name="supplier_name" value="{{ old('supplier_name') }}" maxlength="150" placeholder="Plain text only"></label></div>
<div x-data="lineItems"><div class="section-title"><h2>Medicine items</h2><button type="button" class="button button-secondary button-small" @click="addRow()"><x-icon name="plus" /> Add item</button></div>
<div class="line-items"><template x-for="(row, index) in rows" :key="index"><div class="line-row"><label class="line-medicine">Medicine<select :name="`items[${index}][medicine_id]`" x-model="row.medicine_id" @change="chooseMedicine(index)" required><option value="">Select medicine</option><template x-for="medicine in medicines" :key="medicine.id"><option :value="medicine.id" x-text="medicine.name"></option></template></select></label><label>Quantity<input :name="`items[${index}][quantity]`" type="number" min="1" max="1000000" x-model="row.quantity" required></label><label>Unit cost (৳)<input :name="`items[${index}][unit_price]`" type="number" min="0.01" step="0.01" x-model="row.unit_price" required></label><div><span style="display:block;font-size:12px;color:#7a7a7a;margin-bottom:4px">Line total</span><div class="line-total" x-text="format(lineTotal(index))"></div></div><button type="button" class="remove-line" @click="removeRow(index)" aria-label="Remove line">×</button></div></template></div>
<div style="display:flex;justify-content:flex-end;margin-top:18px;padding-top:15px;border-top:1px solid #e0e0e0;font-size:16px"><strong>Purchase total: <span x-text="format(grandTotal())" style="font-size:21px;margin-left:8px"></span></strong></div></div>
<label>Notes<textarea name="notes" maxlength="2000" placeholder="Optional reference or receiving notes">{{ old('notes') }}</textarea></label>
<div class="form-actions"><button class="button button-primary" type="submit">Save purchase</button><a class="button button-muted" href="{{ route('purchases.index') }}">Cancel</a></div>
</form></div>
@endsection
