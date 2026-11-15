<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('auth.title') }} · Homeo-Hall</title>@include('partials.pwa-head')@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="login-page"><main class="login-shell">
    <section class="login-story">
        <a href="{{ route('login') }}" class="brand"><img class="brand-mark brand-mark-image" src="{{ asset('icons/brand-mark.png') }}" alt="" width="40" height="40"><span><strong>Homeo-Hall</strong><small>{{ __('common.chamber_management') }}</small></span></a>
        <div><p class="eyebrow hero-eyebrow">{{ __('auth.hero_eyebrow') }}</p><h1>{{ __('auth.hero_title') }}</h1><p>{{ __('auth.hero_copy') }}</p></div>
        <div class="story-foot">{{ __('auth.story_foot') }}</div>
    </section>
    <section class="login-card">
        <form method="POST" action="{{ route('language.update') }}" class="login-language">@csrf<div class="language-options" role="group" aria-label="{{ __('common.language_switch') }}"><button type="submit" name="locale" value="en" class="language-option {{ app()->getLocale() === 'en' ? 'is-active' : '' }}" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">{{ app()->getLocale() === 'bn' ? __('common.english') : 'EN' }}</button><span class="language-divider" aria-hidden="true">|</span><button type="submit" name="locale" value="bn" class="language-option {{ app()->getLocale() === 'bn' ? 'is-active' : '' }}" aria-pressed="{{ app()->getLocale() === 'bn' ? 'true' : 'false' }}">বাংলা</button></div></form>
        <p class="eyebrow">{{ __('auth.secure_sign_in') }}</p><h1>{{ __('auth.welcome_back') }}</h1><p class="login-copy">{{ __('auth.copy') }}</p>
        @if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}" class="form-stack">@csrf
            <label>{{ __('common.email') }}<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
            <label>{{ __('common.password') }}<input name="password" type="password" autocomplete="current-password" required></label>
            <label class="checkbox-line"><input name="remember" type="checkbox" value="1">{{ __('auth.remember') }}</label>
            <button class="button button-primary button-wide" type="submit">{{ __('auth.title') }}</button>
        </form>
        <p class="fine-note">{{ __('auth.fine_note') }}</p>
    </section>
</main>
<script>window.HPMS_LOCALE = @json(['locale' => app()->getLocale(), 'messages' => ['install_share' => __('common.install_share_text')]]);</script>
</body></html>
