@extends('layouts.app')
@section('title', __('dashboard.title'))
@section('content')
@php($daypart = now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening'))
<section class="dashboard-hero">
    <div><p class="eyebrow hero-eyebrow">{{ __('dashboard.eyebrow') }}</p><h1>{{ __('dashboard.greeting', ['greeting' => __('dashboard.'.$daypart), 'name' => explode(' ', auth()->user()->name)[0]]) }}</h1><p>{{ __('dashboard.intro') }}</p><div class="hero-actions"><a class="button button-primary" href="{{ route('sales.create') }}">{{ __('dashboard.record_sale') }}</a><a class="button hero-outline" href="{{ route('reports.index') }}">{{ __('dashboard.view_reports') }}</a></div></div>
    <div class="hero-metric"><small>{{ __('dashboard.month_net_profit') }}</small><strong>{{ money($monthly['net_profit']) }}</strong><span>{{ __('dashboard.month_net_profit_note') }}</span></div>
</section>

<div class="stat-grid">
    <x-stat-card :label="__('dashboard.today_sales')" :value="money($daily['sales'])" :note="__('dashboard.sale_records', ['count' => localized_number($daily['sales_count'])])" />
    <x-stat-card :label="__('dashboard.today_purchases')" :value="money($daily['purchases'])" :note="__('dashboard.purchase_records', ['count' => localized_number($daily['purchases_count'])])" />
    <x-stat-card :label="__('dashboard.today_expenses')" :value="money($daily['expenses'])" :note="__('dashboard.expense_records', ['count' => localized_number($daily['expenses_count'])])" />
    <x-stat-card :label="__('dashboard.today_gross_profit')" :value="money($daily['gross_profit'])" :note="__('dashboard.gross_note')" />
    <x-stat-card :label="__('dashboard.today_net_profit')" :value="money($daily['net_profit'])" :note="__('dashboard.net_note')" />
    <x-stat-card :label="__('dashboard.total_medicines')" :value="localized_number($metrics['medicines'])" :note="__('dashboard.active_inactive_note')" />
    <x-stat-card :label="__('dashboard.total_units')" :value="localized_number($metrics['units'])" :note="__('dashboard.across_medicines_note')" />
    <x-stat-card :label="__('dashboard.low_stock')" :value="localized_number($metrics['low_stock'])" :note="__('dashboard.at_or_below_note')" />
    <x-stat-card :label="__('dashboard.out_of_stock')" :value="localized_number($metrics['out_of_stock'])" :note="__('dashboard.reorder_note')" />
    <x-stat-card :label="__('dashboard.inventory_value')" :value="money($daily['inventory_value'])" :note="__('dashboard.weighted_cost_note')" />
</div>

<div class="card" style="margin-bottom:18px">
    <div class="section-title"><h2>{{ __('dashboard.this_month') }}</h2><a href="{{ route('reports.monthly') }}">{{ __('dashboard.open_monthly') }}</a></div>
    <div class="summary-grid">
        <div class="summary-item"><small>{{ __('dashboard.sales') }}</small><strong>{{ money($monthly['sales']) }}</strong></div>
        <div class="summary-item"><small>{{ __('dashboard.purchases') }}</small><strong>{{ money($monthly['purchases']) }}</strong></div>
        <div class="summary-item"><small>{{ __('dashboard.other_expenses') }}</small><strong>{{ money($monthly['expenses']) }}</strong></div>
        <div class="summary-item"><small>{{ __('dashboard.cogs') }}</small><strong>{{ money($monthly['cogs']) }}</strong></div>
        <div class="summary-item"><small>{{ __('dashboard.gross_profit') }}</small><strong>{{ money($monthly['gross_profit']) }}</strong></div>
        <div class="summary-item"><small>{{ __('dashboard.net_operating_profit') }}</small><strong>{{ money($monthly['net_profit']) }}</strong></div>
    </div>
    <div class="quick-actions">
        <a class="button button-primary" href="{{ route('medicines.create') }}"><x-icon name="plus" /> {{ __('dashboard.add_medicine') }}</a>
        <a class="button button-secondary" href="{{ route('purchases.create') }}">{{ __('dashboard.record_purchase') }}</a>
        <a class="button button-secondary" href="{{ route('sales.create') }}">{{ __('dashboard.record_sale') }}</a>
        <a class="button button-secondary" href="{{ route('expenses.create') }}">{{ __('dashboard.add_expense') }}</a>
        <a class="button button-muted" href="{{ route('reports.index') }}">{{ __('dashboard.view_reports') }}</a>
    </div>
</div>

<div class="content-grid equal">
    <div class="card"><div class="section-title"><h2>{{ __('dashboard.monthly_sales') }}</h2><span class="status status-completed">{{ __('dashboard.last_six_months') }}</span></div><x-chart id="sales-chart" type="line" :caption="__('dashboard.sales_caption')" :labels="$chart->pluck('label')" :series="[__('dashboard.series_sales') => $chart->pluck('sales')]" /></div>
    <div class="card"><div class="section-title"><h2>{{ __('dashboard.monthly_expenses') }}</h2><span class="status status-completed">{{ __('dashboard.last_six_months') }}</span></div><x-chart id="expenses-chart" type="bar" :caption="__('dashboard.expenses_caption')" :labels="$chart->pluck('label')" :series="[__('dashboard.series_other_expenses') => $chart->pluck('expenses')]" /></div>
</div>
<div class="content-grid equal">
    <div class="card"><div class="section-title"><h2>{{ __('dashboard.sales_vs_purchases') }}</h2><span class="status status-completed">{{ __('dashboard.cash_flow') }}</span></div><x-chart id="comparison-chart" type="bar" :caption="__('dashboard.comparison_caption')" :labels="$chart->pluck('label')" :series="[__('dashboard.series_sales') => $chart->pluck('sales'), __('dashboard.series_purchases') => $chart->pluck('purchases')]" /></div>
    <div class="card"><div class="section-title"><h2>{{ __('dashboard.net_profit_trend') }}</h2><span class="status status-completed">{{ __('dashboard.net_operating') }}</span></div><x-chart id="profit-chart" type="line" :caption="__('dashboard.profit_caption')" :labels="$chart->pluck('label')" :series="[__('dashboard.series_net_profit') => $chart->pluck('profit')]" /></div>
</div>

<div class="content-grid">
    <div class="card"><div class="section-title"><h2>{{ __('dashboard.recent_activity') }}</h2></div>
        @if($activity->isEmpty())<div class="empty-state"><strong>{{ __('dashboard.no_activity_title') }}</strong>{{ __('dashboard.no_activity_copy') }}</div>@else<div class="activity-list">@foreach($activity as $item)<a class="activity-item" href="{{ $item['url'] }}"><span class="activity-dot"></span><span><strong>{{ $item['label'] }}</strong><small>{{ $item['detail'] }}</small></span><small class="activity-date">{{ localized_date($item['date'], 'd M') }}</small></a>@endforeach</div>@endif
    </div>
    <div class="card"><div class="section-title"><h2>{{ __('dashboard.inventory_attention') }}</h2><a href="{{ route('medicines.index', ['status' => 'low']) }}">{{ __('dashboard.review_stock') }}</a></div>
        @php($attention = \App\Models\Medicine::query()->whereColumn('stock_quantity', '<=', 'minimum_stock')->orderBy('stock_quantity')->limit(6)->get())
        @if($attention->isEmpty())<div class="empty-state"><strong>{{ __('dashboard.healthy_title') }}</strong>{{ __('dashboard.healthy_copy') }}</div>@else<div class="activity-list">@foreach($attention as $medicine)<a class="activity-item" href="{{ route('medicines.show', $medicine) }}"><span class="activity-dot" style="background:{{ $medicine->stock_quantity === 0 ? '#a12d27' : '#b7791f' }}"></span><span><strong>{{ $medicine->name }} {{ $medicine->potency }}</strong><small>{{ __('dashboard.remaining_minimum', ['stock' => localized_number($medicine->stock_quantity), 'unit' => default_label($medicine->unit, 'units'), 'minimum' => localized_number($medicine->minimum_stock)]) }}</small></span></a>@endforeach</div>@endif
    </div>
</div>

@foreach([
    'sales-chart' => ['label' => __('dashboard.series_sales'), 'key' => 'sales', 'role' => 'accent'],
    'expenses-chart' => ['label' => __('dashboard.series_other_expenses'), 'key' => 'expenses', 'role' => 'neutral'],
    'comparison-chart' => null,
    'profit-chart' => ['label' => __('dashboard.series_net_profit'), 'key' => 'profit', 'role' => 'positive'],
] as $id => $series)
    <script type="application/json" id="{{ $id }}">
        @if($series)
            {!! json_encode(['labels' => $chart->pluck('label'), 'datasets' => [['label' => $series['label'], 'data' => $chart->pluck($series['key']), 'role' => $series['role'], 'borderWidth' => 2, 'tension' => .3, 'fill' => false]]]) !!}
        @else
            {!! json_encode(['labels' => $chart->pluck('label'), 'datasets' => [['label' => __('dashboard.series_sales'), 'data' => $chart->pluck('sales'), 'role' => 'accent', 'borderRadius' => 5], ['label' => __('dashboard.series_purchases'), 'data' => $chart->pluck('purchases'), 'role' => 'neutral', 'borderRadius' => 5]]]) !!}
        @endif
    </script>
@endforeach
@endsection
