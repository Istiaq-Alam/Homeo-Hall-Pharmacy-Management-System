<div class="report-actions" style="margin-bottom:18px">
    <a class="button {{ request()->routeIs('reports.index') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.index') }}">{{ __('reports.custom_range') }}</a>
    <a class="button {{ request()->routeIs('reports.daily') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.daily') }}">{{ __('reports.daily') }}</a>
    <a class="button {{ request()->routeIs('reports.monthly') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.monthly') }}">{{ __('reports.monthly') }}</a>
    <a class="button {{ request()->routeIs('reports.yearly') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.yearly') }}">{{ __('reports.yearly') }}</a>
    <a class="button {{ request()->routeIs('reports.inventory') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.inventory') }}">{{ __('reports.inventory') }}</a>
    <a class="button {{ request()->routeIs('reports.medicine-sales') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.medicine-sales') }}">{{ __('reports.medicine_sales') }}</a>
    <a class="button {{ request()->routeIs('reports.expenses') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.expenses') }}">{{ __('reports.expenses') }}</a>
    <a class="button {{ request()->routeIs('reports.low-stock') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.low-stock') }}">{{ __('reports.low_stock') }}</a>
    <a class="button {{ request()->routeIs('reports.stock-movements') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.stock-movements') }}">{{ __('reports.stock_movements') }}</a>
</div>
