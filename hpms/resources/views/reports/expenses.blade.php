@extends('layouts.app')
@section('title', __('reports.expense_report_title'))
@section('content')
<x-page-heading :title="__('reports.expense_report_title')" :description="__('reports.expense_report_description')" />
@include('reports.partials.tabs')
<form class="filters no-print" method="GET" action="{{ route('reports.expenses') }}"><label>{{ __('reports.from') }}<input name="from" type="date" value="{{ $from }}" required></label><label>{{ __('reports.to') }}<input name="to" type="date" value="{{ $to }}" required></label><button class="button button-muted" type="submit">{{ __('reports.generate') }}</button><button class="button button-muted" type="button" onclick="window.print()"><x-icon name="printer" /> {{ __('common.print') }}</button></form>
<div class="print-only"><h2>{{ __('reports.expense_report') }}</h2><p>{{ __('reports.range', ['from' => localized_date($from), 'to' => localized_date($to)]) }}</p></div>
<div class="table-wrap"><table class="table"><thead><tr><th>{{ __('reports.expense_category') }}</th><th class="numeric">{{ __('reports.transactions') }}</th><th class="numeric">{{ __('reports.total_amount') }}</th></tr></thead><tbody>@forelse($categories as $category)<tr><td><strong>{{ default_label($category->name, 'expense_categories') }}</strong></td><td class="numeric">{{ localized_number($category->transaction_count) }}</td><td class="numeric">{{ money($category->total_amount ?? 0) }}</td></tr>@empty<tr><td colspan="3" class="empty-state">{{ __('reports.no_expense_categories') }}</td></tr>@endforelse</tbody></table></div>
@endsection
