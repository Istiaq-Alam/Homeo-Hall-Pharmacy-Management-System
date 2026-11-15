<div class="report-actions" style="margin-bottom:18px">
    <a class="button {{ request()->routeIs('reports.index') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.index') }}">Custom range</a>
    <a class="button {{ request()->routeIs('reports.daily') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.daily') }}">Daily</a>
    <a class="button {{ request()->routeIs('reports.monthly') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.monthly') }}">Monthly</a>
    <a class="button {{ request()->routeIs('reports.yearly') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.yearly') }}">Yearly</a>
    <a class="button {{ request()->routeIs('reports.inventory') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.inventory') }}">Inventory</a>
    <a class="button {{ request()->routeIs('reports.medicine-sales') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.medicine-sales') }}">Medicine-wise sales</a>
    <a class="button {{ request()->routeIs('reports.expenses') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.expenses') }}">Expenses</a>
    <a class="button {{ request()->routeIs('reports.low-stock') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.low-stock') }}">Low stock</a>
    <a class="button {{ request()->routeIs('reports.stock-movements') ? 'button-primary' : 'button-muted' }} button-small" href="{{ route('reports.stock-movements') }}">Stock movements</a>
</div>
