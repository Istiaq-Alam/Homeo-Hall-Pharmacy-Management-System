@php
    $pwaTitle = $appSettings['chamber_name'] ?? config('app.name', 'Homeo-Hall');
    $brand = $appSettings['brand_assets'] ?? [];

    // Every user gets the bundled launch screens from public/icons/splash: the
    // artwork already carries the Homeo-Hall name, so nothing is drawn over it.
    // Sizes are listed at exact device pixels so iOS picks a native splash
    // instead of showing a blank white screen; ?v=2 retires the previous
    // artwork iOS may still have cached against the bare file name.
    $splashScreens = collect(\App\Support\BrandAssets::SPLASH)->map(fn (array $screen) => [
        'w' => $screen[0],
        'h' => $screen[1],
        'dpr' => $screen[2],
        'size' => ($screen[0] * $screen[2]).'x'.($screen[1] * $screen[2]),
        'orientation' => $screen[0] > $screen[1] ? 'landscape' : 'portrait',
    ]);
@endphp
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="theme-color" content="{{ auth()->check() ? config('themes.'.auth()->user()->activeTheme().'.canvas', '#f5f5f7') : '#f5f5f7' }}">
<meta name="application-name" content="{{ $pwaTitle }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $pwaTitle }}">
<meta name="format-detection" content="telephone=no">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $brand['favicon32'] ?? asset('icons/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="48x48" href="{{ $brand['favicon48'] ?? asset('icons/favicon-48.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $brand['appleTouch'] ?? asset('icons/apple-touch-icon.png') }}">
@foreach ($splashScreens as $splash)
<link rel="apple-touch-startup-image" media="screen and (device-width: {{ $splash['w'] }}px) and (device-height: {{ $splash['h'] }}px) and (-webkit-device-pixel-ratio: {{ $splash['dpr'] }}) and (orientation: {{ $splash['orientation'] }})" href="{{ asset('icons/splash/'.$splash['size'].'.png').'?v=2' }}">
@endforeach
