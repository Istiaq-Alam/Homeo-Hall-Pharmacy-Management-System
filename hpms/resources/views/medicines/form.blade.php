@extends('layouts.app')
@section('title', $medicine->exists ? __('medicines.edit') : __('medicines.add'))
@section('content')
<div class="breadcrumb"><a href="{{ route('medicines.index') }}">{{ __('medicines.title') }}</a> / {{ $medicine->exists ? __('common.edit') : __('common.add') }}</div>
<x-page-heading :title="$medicine->exists ? __('medicines.edit') : __('medicines.add')" :description="__('medicines.form_description')" />
<div class="card form-card"><form method="POST" action="{{ $medicine->exists ? route('medicines.update', $medicine) : route('medicines.store') }}" class="form-stack">@csrf @if($medicine->exists) @method('PUT') @endif
<div class="form-grid">
<label>{{ __('medicines.name') }}<input name="name" value="{{ old('name', $medicine->name) }}" required maxlength="150"></label>
<label>{{ __('medicines.potency') }}<input name="potency" value="{{ old('potency', $medicine->potency) }}" placeholder="{{ __('medicines.potency_placeholder') }}"></label>
<label>{{ __('medicines.category') }}<select name="category_id" required><option value="">{{ __('common.select_category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $medicine->category_id) == $category->id)>{{ default_label($category->name, 'medicine_categories') }}</option>@endforeach</select></label>
<label>{{ __('medicines.form') }}<select name="form" required>@foreach(['Liquid','Globules','Tablets','Powder','Ointment','Other'] as $form)<option value="{{ $form }}" @selected(old('form', $medicine->form) === $form)>{{ default_label($form, 'forms') }}</option>@endforeach</select></label>
<label>{{ __('medicines.unit') }}<select name="unit" required>@foreach(['Bottle','Packet','Vial','Tube','Box','Piece'] as $unit)<option value="{{ $unit }}" @selected(old('unit', $medicine->unit) === $unit)>{{ default_label($unit, 'units') }}</option>@endforeach</select></label>
<label>{{ __('medicines.minimum_stock') }}<input name="minimum_stock" type="number" min="0" step="1" value="{{ old('minimum_stock', $medicine->minimum_stock ?? ($appSettings['low_stock_default'] ?? 5)) }}" required></label>
<label>{{ __('medicines.purchase_price') }}<input name="purchase_price" type="number" min="0" step="0.01" value="{{ old('purchase_price', $medicine->purchase_price ?? 0) }}" required></label>
<label>{{ __('medicines.selling_price') }} (৳)<input name="selling_price" type="number" min="0" step="0.01" value="{{ old('selling_price', $medicine->selling_price ?? 0) }}" required></label>
</div>
<label>{{ __('medicines.description_label') }}<textarea name="description" maxlength="4000" placeholder="{{ __('medicines.description_placeholder') }}">{{ old('description', $medicine->description) }}</textarea></label>
@if($medicine->exists)<p class="report-note">{!! __('medicines.current_stock_note', ['stock' => localized_number($medicine->stock_quantity), 'unit' => e(default_label($medicine->unit, 'units')), 'link' => '<a href="'.e(route('medicines.adjustments.create')).'">'.e(__('medicines.stock_adjustment_link')).'</a>']) !!}</p>@endif
<div class="form-actions"><button class="button button-primary" type="submit">{{ $medicine->exists ? __('common.save_changes') : __('medicines.add') }}</button><a class="button button-muted" href="{{ route('medicines.index') }}">{{ __('common.cancel') }}</a></div>
</form></div>
@endsection
