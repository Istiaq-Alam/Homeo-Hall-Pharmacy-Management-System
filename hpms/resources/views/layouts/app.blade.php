<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $appSettings['chamber_name'] ?? 'Homeo-Hall' }}</title>
    @include('partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-parchment text-ink antialiased" x-data="{
    menuOpen: false,
    setMenu(open) {
        if (this.menuOpen === open) return;
        this.menuOpen = open;
        if (open) {
            this.$nextTick(() => document.getElementById('sidebar-close')?.focus({ preventScroll: true }));
        } else if (window.matchMedia('(max-width: 1023px), (orientation: landscape) and (max-height: 500px)').matches) {
            this.$nextTick(() => document.getElementById('menu-toggle')?.focus({ preventScroll: true }));
        }
    },
}" :class="{ 'nav-open': menuOpen }" @sidebar-control.window="setMenu($event.detail.open)" @keydown.escape.window="setMenu(false)" @resize.window.debounce.200ms="if (! window.matchMedia('(max-width: 1023px), (orientation: landscape) and (max-height: 500px)').matches) setMenu(false)">
    <div class="app-shell min-h-screen lg:flex">
        <aside id="sidebar" class="sidebar" :class="menuOpen ? 'sidebar-open' : ''">
            <button type="button" id="sidebar-close" class="sidebar-close" @click="setMenu(false)" aria-label="Close navigation"><span aria-hidden="true">×</span></button>
            <a href="{{ route('dashboard') }}" class="brand">@if(! empty($appSettings['chamber_logo_url']))<img class="brand-logo" src="{{ $appSettings['chamber_logo_url'] }}" alt="{{ $appSettings['chamber_name'] ?? 'Homeo-Hall' }} logo">@else<span class="brand-mark">H</span>@endif<span><strong>{{ $appSettings['chamber_name'] ?? 'Homeo-Hall' }}</strong><small>Chamber management</small></span></a>
            <nav class="nav-list" aria-label="Main navigation">
                <a href="{{ route('dashboard') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}"><span><x-icon name="dashboard" /></span>Dashboard</a>
                <a href="{{ route('medicines.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('medicines.*') ? 'nav-active' : '' }}"><span><x-icon name="medicine" /></span>Medicines</a>
                <a href="{{ route('purchases.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('purchases.*') ? 'nav-active' : '' }}"><span><x-icon name="purchase" /></span>Purchases</a>
                <a href="{{ route('sales.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('sales.*') ? 'nav-active' : '' }}"><span><x-icon name="sale" /></span>Sales</a>
                <a href="{{ route('expenses.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('expenses.*') ? 'nav-active' : '' }}"><span><x-icon name="expense" /></span>Expenses</a>
                <a href="{{ route('reports.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('reports.*') ? 'nav-active' : '' }}"><span><x-icon name="report" /></span>Reports</a>
                <a href="{{ route('settings.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('settings.*') ? 'nav-active' : '' }}"><span><x-icon name="settings" /></span>Settings</a>
            </nav>
            <div class="sidebar-bottom"><div class="owner-pill">@if(auth()->user()->avatar_url)<img class="avatar avatar-photo" src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }} profile picture">@else<span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>@endif<span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->designation ?: 'Chamber administrator' }}</small></span></div><button type="button" id="install-app" class="nav-link install-link" hidden><span><x-icon name="download" /></span>Install app</button><form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout-link" type="submit"><span><x-icon name="logout" /></span>Sign out</button></form></div>
        </aside>
        <div class="sidebar-backdrop" x-show="menuOpen" x-cloak @click="menuOpen = false" aria-hidden="true"></div>
        <div class="app-main flex-1 min-w-0">
            <header class="topbar"><button type="button" id="menu-toggle" class="menu-toggle" @click="setMenu(!menuOpen)" :aria-expanded="menuOpen.toString()" aria-controls="sidebar" aria-label="Toggle navigation"><x-icon name="menu" /></button><div class="topbar-title">@yield('title', 'Dashboard')</div><div class="topbar-date">{{ now()->timezone(config('app.timezone'))->format('l, d M Y') }}</div></header>
            <main class="page-wrap">
                @if(session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
                @if($errors->any())<div class="notice notice-error" role="alert"><strong>Please review the form:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </main>
        </div>
    </div>
    <dialog id="install-dialog" class="install-dialog" aria-labelledby="install-dialog-title">
        <form method="dialog"><button type="submit" class="dialog-close" aria-label="Close install instructions"><span aria-hidden="true">×</span></button></form>
        <h2 id="install-dialog-title">Install Homeo-Hall</h2>
        <p>Add the chamber app to your Home Screen for a full-screen, app-like experience.</p>
        <ol><li>Tap the <strong>Share</strong> button in Safari.</li><li>Choose <strong>Add to Home Screen</strong>.</li><li>Tap <strong>Add</strong> to finish.</li></ol>
        <form method="dialog"><button type="submit" class="button button-primary button-wide">Got it</button></form>
    </dialog>
    <script>
        window.confirmAction = (message) => window.confirm(message || 'Are you sure you want to continue?');
    </script>
</body>
</html>
