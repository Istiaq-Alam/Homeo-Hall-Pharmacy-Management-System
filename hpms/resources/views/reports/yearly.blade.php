@extends('layouts.app')
@section('title', 'Yearly report')
@section('content')
<x-page-heading title="Yearly report" description="Month-by-month sales, purchases, COGS, expenses, and operating profit." />
@include('reports.partials.tabs')
<form class="filters no-print" method="GET" action="{{ route('reports.yearly') }}"><label>Year<input name="year" type="number" min="2000" max="2100" value="{{ $year }}" required></label><button class="button button-muted" type="submit">Generate report</button></form>
<div class="print-only"><h2>Homeo-Hall yearly report</h2><p>{{ $year }}</p></div>
<div class="card" style="margin-bottom:18px"><div class="section-title"><h2>{{ $year }} monthly trend</h2></div><div class="report-chart"><canvas data-chart="yearly-chart" data-type="line"></canvas></div></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Month</th><th class="numeric">Sales</th><th class="numeric">Purchases</th><th class="numeric">COGS</th><th class="numeric">Other expenses</th><th class="numeric">Gross profit</th><th class="numeric">Net operating profit</th></tr></thead><tbody>@foreach($months as $month)<tr><td><strong>{{ $month['label'] }} {{ $year }}</strong></td><td class="numeric">৳{{ number_format($month['sales'], 2) }}</td><td class="numeric">৳{{ number_format($month['purchases'], 2) }}</td><td class="numeric">৳{{ number_format($month['cogs'], 2) }}</td><td class="numeric">৳{{ number_format($month['expenses'], 2) }}</td><td class="numeric">৳{{ number_format($month['gross_profit'], 2) }}</td><td class="numeric">৳{{ number_format($month['net_profit'], 2) }}</td></tr>@endforeach</tbody></table></div>
<script type="application/json" id="yearly-chart">{!! json_encode(['labels' => $months->pluck('label'), 'datasets' => [['label' => 'Sales', 'data' => $months->pluck('sales'), 'borderColor' => '#0066cc', 'backgroundColor' => '#0066cc', 'tension' => .3], ['label' => 'COGS', 'data' => $months->pluck('cogs'), 'borderColor' => '#7a7a7a', 'backgroundColor' => '#7a7a7a', 'tension' => .3], ['label' => 'Net profit', 'data' => $months->pluck('net_profit'), 'borderColor' => '#27673a', 'backgroundColor' => '#27673a', 'tension' => .3]]]) !!}</script>
@endsection
