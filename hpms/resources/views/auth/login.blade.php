<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Sign in · Homeo-Hall</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="login-page"><main class="login-card"><div class="login-logo"><span class="brand-mark">H</span></div><p class="eyebrow">HOMEOPATHIC CHAMBER MANAGEMENT</p><h1>Welcome back</h1><p class="login-copy">Sign in to manage your inventory and daily records.</p>
@if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}" class="form-stack">@csrf
<label>Email address<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
<label>Password<input name="password" type="password" autocomplete="current-password" required></label>
<label class="checkbox-line"><input name="remember" type="checkbox" value="1">Keep me signed in on this device</label>
<button class="button button-primary button-wide" type="submit">Sign in</button></form><p class="fine-note">Private owner access · Registration is disabled</p></main></body></html>
