@extends('layouts.app')
@section('title', __('medicines.title'))
@section('content')
<x-page-heading :title="__('medicines.title')" :description="__('medicines.description')">
    <x-slot:action><a class="button button-secondary" href="{{ route('medicines.categories') }}">{{ __('medicines.categories') }}</a><a class="button button-secondary" href="{{ route('medicines.adjustments.create') }}">{{ __('medicines.stock_adjustment') }}</a><a class="button button-primary" href="{{ route('medicines.create') }}"><x-icon name="plus" /> {{ __('medicines.add') }}</a></x-slot:action>
</x-page-heading>
<form class="filters" method="GET" action="{{ route('medicines.index') }}">
    <label>{{ __('common.search') }}<input name="q" value="{{ request('q') }}" placeholder="{{ __('medicines.search_placeholder') }}"></label>
    <label>{{ __('common.category') }}<select name="category"><option value="">{{ __('medicines.all_categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ default_label($category->name, 'medicine_categories') }}</option>@endforeach</select></label>
    <label>{{ __('medicines.form') }}<select name="form"><option value="">{{ __('medicines.all_forms') }}</option>@foreach(['Liquid','Globules','Tablets','Powder','Ointment','Other'] as $form)<option value="{{ $form }}" @selected(request('form') === $form)>{{ default_label($form, 'forms') }}</option>@endforeach</select></label>
    <label>{{ __('medicines.current_stock') }}<select name="status"><option value="">{{ __('medicines.all_stock') }}</option><option value="low" @selected(request('status') === 'low')>{{ __('medicines.low_stock') }}</option><option value="out" @selected(request('status') === 'out')>{{ __('medicines.out_of_stock') }}</option></select></label>
    <label>{{ __('medicines.sort') }}<select name="sort"><option value="name">{{ __('medicines.sort_name') }}</option><option value="stock" @selected(request('sort') === 'stock')>{{ __('medicines.lowest_stock') }}</option><option value="price" @selected(request('sort') === 'price')>{{ __('medicines.highest_price') }}</option><option value="date" @selected(request('sort') === 'date')>{{ __('medicines.newest') }}</option></select></label>
    <button class="button button-muted" type="submit">{{ __('common.apply_filters') }}</button>
</form>
@if($medicines->isEmpty())<div class="card empty-state"><strong>{{ __('medicines.no_title') }}</strong>{{ __('medicines.no_copy') }}</div>@else
<div class="table-wrap"><table class="table"><thead><tr><th>{{ __('medicines.title') }}</th><th>{{ __('medicines.category') }}</th><th>{{ __('medicines.form_unit') }}</th><th class="numeric">{{ __('medicines.selling_price') }}</th><th class="numeric">{{ __('medicines.current_stock') }}</th><th>{{ __('common.status') }}</th><th></th></tr></thead><tbody>
@foreach($medicines as $medicine)
<tr><td><a href="{{ route('medicines.show', $medicine) }}"><strong>{{ $medicine->name }}</strong></a><br><small>{{ $medicine->potency ?: __('medicines.no_potency') }}</small></td><td>{{ default_label($medicine->category->name, 'medicine_categories') }}</td><td>{{ default_label($medicine->form, 'forms') }} · {{ default_label($medicine->unit, 'units') }}</td><td class="numeric">{{ money($medicine->selling_price) }}</td><td class="numeric">{{ localized_number($medicine->stock_quantity) }}</td><td>@include('medicines.partials.status', ['medicine' => $medicine])</td><td><div class="table-actions"><a href="{{ route('medicines.edit', $medicine) }}">{{ __('common.edit') }}</a></div></td></tr>
@endforeach
</tbody></table></div><div class="pagination"><span>{{ __('common.showing', ['first' => localized_number($medicines->firstItem()), 'last' => localized_number($medicines->lastItem()), 'total' => localized_number($medicines->total())]) }}</span>{{ $medicines->links() }}</div>@endif
@endsection
