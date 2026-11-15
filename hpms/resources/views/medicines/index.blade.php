@extends('layouts.app')
@section('title', 'Medicines')
@section('content')
<x-page-heading title="Medicines" description="Every potency is tracked as its own inventory item.">
    <x-slot:action><a class="button button-secondary" href="{{ route('medicines.categories') }}">Categories</a><a class="button button-secondary" href="{{ route('medicines.adjustments.create') }}">Stock adjustment</a><a class="button button-primary" href="{{ route('medicines.create') }}"><x-icon name="plus" /> Add medicine</a></x-slot:action>
</x-page-heading>
<form class="filters" method="GET" action="{{ route('medicines.index') }}">
    <label>Search<input name="q" value="{{ request('q') }}" placeholder="Name or potency"></label>
    <label>Category<select name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <label>Form<select name="form"><option value="">All forms</option>@foreach(['Liquid','Globules','Tablets','Powder','Ointment','Other'] as $form)<option @selected(request('form') === $form)>{{ $form }}</option>@endforeach</select></label>
    <label>Stock<select name="status"><option value="">All stock</option><option value="low" @selected(request('status') === 'low')>Low stock</option><option value="out" @selected(request('status') === 'out')>Out of stock</option></select></label>
    <label>Sort<select name="sort"><option value="name">Name</option><option value="stock" @selected(request('sort') === 'stock')>Lowest stock</option><option value="price" @selected(request('sort') === 'price')>Highest selling price</option><option value="date" @selected(request('sort') === 'date')>Newest added</option></select></label>
    <button class="button button-muted" type="submit">Apply filters</button>
</form>
@if($medicines->isEmpty())<div class="card empty-state"><strong>No medicines found</strong>Add a medicine or change your filters to see inventory here.</div>@else
<div class="table-wrap"><table class="table"><thead><tr><th>Medicine</th><th>Category</th><th>Form / unit</th><th class="numeric">Selling price</th><th class="numeric">Stock</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($medicines as $medicine)
<tr><td><a href="{{ route('medicines.show', $medicine) }}"><strong>{{ $medicine->name }}</strong></a><br><small>{{ $medicine->potency ?: 'No potency' }}</small></td><td>{{ $medicine->category->name }}</td><td>{{ $medicine->form }} · {{ $medicine->unit }}</td><td class="numeric">৳{{ number_format((float) $medicine->selling_price, 2) }}</td><td class="numeric">{{ number_format($medicine->stock_quantity) }}</td><td>@include('medicines.partials.status', ['medicine' => $medicine])</td><td><div class="table-actions"><a href="{{ route('medicines.edit', $medicine) }}">Edit</a></div></td></tr>
@endforeach
</tbody></table></div><div class="pagination"><span>Showing {{ $medicines->firstItem() }}–{{ $medicines->lastItem() }} of {{ $medicines->total() }}</span>{{ $medicines->links() }}</div>@endif
@endsection
