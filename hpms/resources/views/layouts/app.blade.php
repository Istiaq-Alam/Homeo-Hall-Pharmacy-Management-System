@php
    $layoutUser = auth()->user();
    $layoutChamber = app(\App\Support\ChamberContext::class)->chamber();
    $layoutLocale = app()->getLocale();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $layoutLocale) }}" class="app-document" data-theme="{{ $layoutUser->activeTheme() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('dashboard.title')) · {{ $appSettings['chamber_name'] ?? 'Homeo-Hall' }}</title>
    @include('partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-parchment text-ink antialiased" x-data="{
    menuOpen: false,
    setMenu(open) {
        if (this.menuOpen === open) return;
        this.menuOpen = open;
        if (open) {
            this.$nextTick(() => document.querySelector('#sidebar .nav-link')?.focus({ preventScroll: true }));
        } else if (window.matchMedia('(max-width: 1023px), (orientation: landscape) and (max-height: 500px)').matches) {
            this.$nextTick(() => document.getElementById('menu-toggle')?.focus({ preventScroll: true }));
        }
    },
}" :class="{ 'nav-open': menuOpen }" @sidebar-control.window="setMenu($event.detail.open)" @keydown.escape.window="setMenu(false)" @resize.window.debounce.200ms="if (! window.matchMedia('(max-width: 1023px), (orientation: landscape) and (max-height: 500px)').matches) setMenu(false)">
    <a class="skip-link" href="#main">{{ __('common.skip_to_content') }}</a>
    <div class="app-shell min-h-screen lg:flex">
        <aside id="sidebar" class="sidebar" :class="menuOpen ? 'sidebar-open' : ''">
            <a href="{{ route('dashboard') }}" class="brand">@if(! empty($appSettings['chamber_logo_url']))<img class="brand-logo" src="{{ $appSettings['chamber_logo_url'] }}" alt="{{ __('common.logo_alt', ['name' => $appSettings['chamber_name'] ?? 'Homeo-Hall']) }}">@else<img class="brand-mark brand-mark-image" src="{{ asset('icons/brand-mark.png') }}" alt="" width="40" height="40">@endif<span><strong>{{ $appSettings['chamber_name'] ?? 'Homeo-Hall' }}</strong><small>{{ __('common.chamber_management') }}</small></span></a>
            <nav class="nav-list" aria-label="{{ __('common.main_navigation') }}">
                @if($layoutUser->isAdmin())<a href="{{ route('admin.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('admin.*') ? 'nav-active' : '' }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif><span><x-icon name="admin" /></span>{{ __('navigation.administration') }}</a>@endif
                <a href="{{ route('dashboard') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><span><x-icon name="dashboard" /></span>{{ __('navigation.dashboard') }}</a>
                <a href="{{ route('medicines.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('medicines.*') ? 'nav-active' : '' }}" @if(request()->routeIs('medicines.*')) aria-current="page" @endif><span><x-icon name="medicine" /></span>{{ __('navigation.medicines') }}</a>
                <a href="{{ route('purchases.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('purchases.*') ? 'nav-active' : '' }}" @if(request()->routeIs('purchases.*')) aria-current="page" @endif><span><x-icon name="purchase" /></span>{{ __('navigation.purchases') }}</a>
                <a href="{{ route('sales.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('sales.*') ? 'nav-active' : '' }}" @if(request()->routeIs('sales.*')) aria-current="page" @endif><span><x-icon name="sale" /></span>{{ __('navigation.sales') }}</a>
                <a href="{{ route('expenses.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('expenses.*') ? 'nav-active' : '' }}" @if(request()->routeIs('expenses.*')) aria-current="page" @endif><span><x-icon name="expense" /></span>{{ __('navigation.expenses') }}</a>
                <a href="{{ route('reports.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('reports.*') ? 'nav-active' : '' }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif><span><x-icon name="report" /></span>{{ __('navigation.reports') }}</a>
                <a href="{{ route('settings.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('settings.*') ? 'nav-active' : '' }}" @if(request()->routeIs('settings.*')) aria-current="page" @endif><span><x-icon name="settings" /></span>{{ __('navigation.settings') }}</a>
                <a href="{{ route('themes.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('themes.*') ? 'nav-active' : '' }}" @if(request()->routeIs('themes.*')) aria-current="page" @endif><span><x-icon name="themes" /></span>{{ __('navigation.themes') }}</a>
                <a href="{{ route('manual.index') }}" @click="menuOpen = false" class="nav-link {{ request()->routeIs('manual.*') ? 'nav-active' : '' }}" @if(request()->routeIs('manual.*')) aria-current="page" @endif><span><x-icon name="manual" /></span>{{ __('navigation.user_manual') }}</a>
            </nav>
            <div class="sidebar-bottom">
                @if($layoutUser->isAdmin() && $layoutChamber)
                    <a class="active-chamber" href="{{ route('admin.index') }}" aria-label="{{ __('common.switch_chamber_aria', ['name' => $layoutChamber->name]) }}">
                        <span class="active-chamber-icon"><x-icon name="purchase" size="16" /></span>
                        <span class="active-chamber-copy"><small>{{ __('common.managing') }}</small><strong>{{ $layoutChamber->name }}</strong></span>
                        <span class="active-chamber-action">{{ __('common.switch') }}</span>
                    </a>
                @endif
                <div class="owner-pill">@if($layoutUser->avatar_url)<img class="avatar avatar-photo" src="{{ $layoutUser->avatar_url }}" alt="{{ $layoutUser->name }} {{ __('common.profile_picture') }}">@else<span class="avatar">{{ strtoupper(substr($layoutUser->name, 0, 1)) }}</span>@endif<span><strong>{{ $layoutUser->name }}</strong><small>{{ $layoutUser->roleLabel() }}@if($layoutUser->designation) · {{ $layoutUser->designation }}@endif</small></span></div>
                <button type="button" id="install-app" class="nav-link install-link" hidden><span><x-icon name="download" /></span>{{ __('common.install_app') }}</button>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout-link" type="submit"><span><x-icon name="logout" /></span>{{ __('common.sign_out') }}</button></form>
            </div>
        </aside>
        <div class="sidebar-backdrop" x-show="menuOpen" x-cloak @click="menuOpen = false" aria-hidden="true"></div>
        <div class="app-main flex-1 min-w-0">
            <header class="topbar"><button type="button" id="menu-toggle" class="menu-toggle" @click="setMenu(!menuOpen)" :aria-expanded="menuOpen.toString()" aria-controls="sidebar" aria-label="{{ __('common.toggle_navigation') }}"><x-icon name="menu" /></button><div class="topbar-title">@yield('title', __('dashboard.title'))</div><a class="topbar-logo" href="{{ route('dashboard') }}" aria-label="{{ $appSettings['app_name'] ?? config('app.name') }}"><img src="{{ asset('icons/logo-land-header.png') }}" alt="" width="127" height="32" decoding="async"></a><div class="topbar-date">{{ localized_date(now(), 'l, d M Y') }}</div><form method="POST" action="{{ route('language.update') }}" class="language-switch">@csrf<div class="language-options" role="group" aria-label="{{ __('common.language_switch') }}"><button type="submit" name="locale" value="en" class="language-option {{ $layoutLocale === 'en' ? 'is-active' : '' }}" aria-pressed="{{ $layoutLocale === 'en' ? 'true' : 'false' }}" @if($layoutLocale === 'en') title="{{ __('common.current') }}: English" @endif>{{ $layoutLocale === 'bn' ? __('common.english') : 'EN' }}</button><span class="language-divider" aria-hidden="true">|</span><button type="submit" name="locale" value="bn" class="language-option {{ $layoutLocale === 'bn' ? 'is-active' : '' }}" aria-pressed="{{ $layoutLocale === 'bn' ? 'true' : 'false' }}" @if($layoutLocale === 'bn') title="{{ __('common.current') }}: বাংলা" @endif>বাংলা</button></div></form></header>
            <main class="page-wrap" id="main" tabindex="-1">
                @if(session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="notice notice-error" role="alert">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="notice notice-error" role="alert"><strong>{{ __('common.review_form') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </main>
        </div>
    </div>
    <dialog id="install-dialog" class="install-dialog" aria-labelledby="install-dialog-title">
        <form method="dialog"><button type="submit" class="dialog-close" aria-label="{{ __('common.close_dialog') }}"><span aria-hidden="true">×</span></button></form>
        <h2 id="install-dialog-title">{{ __('common.install_title') }}</h2>
        <p>{{ __('common.install_copy') }}</p>
        <ol><li>{!! __('common.install_step_share') !!}</li><li>{!! __('common.install_step_add') !!}</li><li>{!! __('common.install_step_finish') !!}</li></ol>
        <form method="dialog"><button type="submit" class="button button-primary button-wide">{{ __('common.got_it') }}</button></form>
    </dialog>
    <script>
        window.HPMS_LOCALE = {!! json_encode([
            'locale' => $layoutLocale,
            'digits' => $layoutLocale === 'bn' ? '০১২৩৪৫৬৭৮৯' : null,
            'currency' => '৳',
            'messages' => [
                'confirm' => __('common.are_you_sure'),
                'stock_label' => __('sales.stock_label'),
                'install_share' => __('common.install_share'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};
        window.confirmAction = (message) => window.confirm(message || window.HPMS_LOCALE.messages.confirm);
    </script>
</body>
</html>
