@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<section class="dashboard-hero">
    <div><p class="eyebrow hero-eyebrow">CHAMBER OVERVIEW</p><h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}.</h1><p>Here is how the chamber is doing today, with purchases and operating costs kept in their proper places.</p><div class="hero-actions"><a class="button button-primary" href="{{ route('sales.create') }}">Record a sale</a><a class="button hero-outline" href="{{ route('reports.index') }}">View reports</a></div></div>
    <div class="hero-metric"><small>This month’s net operating profit</small><strong>৳{{ number_format($monthly['net_profit'], 2) }}</strong><span>Gross profit minus other chamber expenses</span></div>
</section>

<div class="stat-grid">
    <x-stat-card label="Today's sales" :value="'৳'.number_format($daily['sales'], 2)" :note="$daily['sales_count'].' sale record(s)'" />
    <x-stat-card label="Today's purchases" :value="'৳'.number_format($daily['purchases'], 2)" :note="$daily['purchases_count'].' purchase record(s)'" />
    <x-stat-card label="Today's other expenses" :value="'৳'.number_format($daily['expenses'], 2)" :note="$daily['expenses_count'].' expense record(s)'" />
    <x-stat-card label="Today's estimated gross profit" :value="'৳'.number_format($daily['gross_profit'], 2)" note="Sales revenue minus COGS" />
    <x-stat-card label="Today's estimated net profit" :value="'৳'.number_format($daily['net_profit'], 2)" note="Gross profit minus other expenses" />
    <x-stat-card label="Total medicines" :value="number_format($metrics['medicines'])" note="Active and inactive records" />
    <x-stat-card label="Total stock units" :value="number_format($metrics['units'])" note="Across all medicines" />
    <x-stat-card label="Low-stock medicines" :value="number_format($metrics['low_stock'])" note="At or below minimum, above zero" />
    <x-stat-card label="Out-of-stock medicines" :value="number_format($metrics['out_of_stock'])" note="Needs reordering" />
    <x-stat-card label="Current inventory value" :value="'৳'.number_format($daily['inventory_value'], 2)" note="Stock × weighted-average cost" />
</div>

<div class="card" style="margin-bottom:18px">
    <div class="section-title"><h2>This month</h2><a href="{{ route('reports.monthly') }}">Open monthly report</a></div>
    <div class="summary-grid">
        <div class="summary-item"><small>Sales</small><strong>৳{{ number_format($monthly['sales'], 2) }}</strong></div>
        <div class="summary-item"><small>Purchases</small><strong>৳{{ number_format($monthly['purchases'], 2) }}</strong></div>
        <div class="summary-item"><small>Other expenses</small><strong>৳{{ number_format($monthly['expenses'], 2) }}</strong></div>
        <div class="summary-item"><small>COGS</small><strong>৳{{ number_format($monthly['cogs'], 2) }}</strong></div>
        <div class="summary-item"><small>Gross profit</small><strong>৳{{ number_format($monthly['gross_profit'], 2) }}</strong></div>
        <div class="summary-item"><small>Net operating profit</small><strong>৳{{ number_format($monthly['net_profit'], 2) }}</strong></div>
    </div>
    <div class="quick-actions">
        <a class="button button-primary" href="{{ route('medicines.create') }}"><x-icon name="plus" /> Add medicine</a>
        <a class="button button-secondary" href="{{ route('purchases.create') }}">Record purchase</a>
        <a class="button button-secondary" href="{{ route('sales.create') }}">Record sale</a>
        <a class="button button-secondary" href="{{ route('expenses.create') }}">Add expense</a>
        <a class="button button-muted" href="{{ route('reports.index') }}">View reports</a>
    </div>
</div>

<div class="content-grid equal">
    <div class="card"><div class="section-title"><h2>Monthly sales overview</h2><span class="status status-completed">Last 6 months</span></div><x-chart id="sales-chart" type="line" caption="Line chart of monthly sales for the last six months." :labels="$chart->pluck('label')" :series="['Sales' => $chart->pluck('sales')]" /></div>
    <div class="card"><div class="section-title"><h2>Monthly expense overview</h2><span class="status status-completed">Last 6 months</span></div><x-chart id="expenses-chart" type="bar" caption="Bar chart of other monthly expenses for the last six months." :labels="$chart->pluck('label')" :series="['Other expenses' => $chart->pluck('expenses')]" /></div>
</div>
<div class="content-grid equal">
    <div class="card"><div class="section-title"><h2>Sales vs purchases</h2><span class="status status-completed">Cash flow</span></div><x-chart id="comparison-chart" type="bar" caption="Bar chart comparing monthly sales and purchases for the last six months." :labels="$chart->pluck('label')" :series="['Sales' => $chart->pluck('sales'), 'Purchases' => $chart->pluck('purchases')]" /></div>
    <div class="card"><div class="section-title"><h2>Monthly net profit trend</h2><span class="status status-completed">Net operating</span></div><x-chart id="profit-chart" type="line" caption="Line chart of monthly net operating profit for the last six months." :labels="$chart->pluck('label')" :series="['Net operating profit' => $chart->pluck('profit')]" /></div>
</div>

<div class="content-grid">
    <div class="card"><div class="section-title"><h2>Recent activity</h2></div>
        @if($activity->isEmpty())<div class="empty-state"><strong>No activity yet</strong>Record a sale, purchase, expense, or stock adjustment to see it here.</div>@else<div class="activity-list">@foreach($activity as $item)<a class="activity-item" href="{{ $item['url'] }}"><span class="activity-dot"></span><span><strong>{{ $item['label'] }}</strong><small>{{ $item['detail'] }}</small></span><small class="activity-date">{{ \Illuminate\Support\Carbon::parse($item['date'])->format('d M') }}</small></a>@endforeach</div>@endif
    </div>
    <div class="card"><div class="section-title"><h2>Inventory attention</h2><a href="{{ route('medicines.index', ['status' => 'low']) }}">Review stock</a></div>
        @php($attention = \App\Models\Medicine::query()->whereColumn('stock_quantity', '<=', 'minimum_stock')->orderBy('stock_quantity')->limit(6)->get())
        @if($attention->isEmpty())<div class="empty-state"><strong>Stock looks healthy</strong>No medicine is at its minimum level.</div>@else<div class="activity-list">@foreach($attention as $medicine)<a class="activity-item" href="{{ route('medicines.show', $medicine) }}"><span class="activity-dot" style="background:{{ $medicine->stock_quantity === 0 ? '#a12d27' : '#b7791f' }}"></span><span><strong>{{ $medicine->name }} {{ $medicine->potency }}</strong><small>{{ $medicine->stock_quantity }} {{ $medicine->unit }} remaining · minimum {{ $medicine->minimum_stock }}</small></span></a>@endforeach</div>@endif
    </div>
</div>

@foreach([
    'sales-chart' => ['label' => 'Sales', 'key' => 'sales', 'role' => 'accent'],
    'expenses-chart' => ['label' => 'Other expenses', 'key' => 'expenses', 'role' => 'neutral'],
    'comparison-chart' => null,
    'profit-chart' => ['label' => 'Net operating profit', 'key' => 'profit', 'role' => 'positive'],
] as $id => $series)
    <script type="application/json" id="{{ $id }}">
        @if($series)
            {!! json_encode(['labels' => $chart->pluck('label'), 'datasets' => [['label' => $series['label'], 'data' => $chart->pluck($series['key']), 'role' => $series['role'], 'borderWidth' => 2, 'tension' => .3, 'fill' => false]]]) !!}
        @else
            {!! json_encode(['labels' => $chart->pluck('label'), 'datasets' => [['label' => 'Sales', 'data' => $chart->pluck('sales'), 'role' => 'accent', 'borderRadius' => 5], ['label' => 'Purchases', 'data' => $chart->pluck('purchases'), 'role' => 'neutral', 'borderRadius' => 5]]]) !!}
        @endif
    </script>
@endforeach
@endsection
