<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Sign in · Homeo-Hall</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="login-page"><main class="login-shell">
    <section class="login-story">
        <a href="{{ route('login') }}" class="brand"><span class="brand-mark" style="background:#fff;color:#0066cc">H</span><span><strong>Homeo-Hall</strong><small>Chamber management</small></span></a>
        <div><p class="eyebrow hero-eyebrow">HOMEOPATHIC MEDICINE INVENTORY</p><h1>Inventory, sales, and profit — in one calm place.</h1><p>Keep the chamber’s medicines, purchases, sales, and daily financial records clear and current.</p></div>
        <div class="story-foot">Private owner access · Built for a single homeopathic chamber</div>
    </section>
    <section class="login-card"><p class="eyebrow">SECURE OWNER SIGN IN</p><h1>Welcome back</h1><p class="login-copy">Enter the owner account details to continue.</p>
        @if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}" class="form-stack">@csrf
            <label>Email address<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
            <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
            <label class="checkbox-line"><input name="remember" type="checkbox" value="1">Keep me signed in on this device</label>
            <button class="button button-primary button-wide" type="submit">Sign in</button>
        </form>
        <p class="fine-note">Private, single-owner access only · Registration is disabled</p>
    </section>
</main></body></html>
