@extends('layouts.app')
@section('title', 'Themes')
@section('content')
@php($currentTheme = $themes[$current])
<div class="theme-page"
     x-data="{ preview: null, saved: @js($current), submitting: false, applyTheme(theme) { document.documentElement.dataset.theme = theme; document.querySelector('meta[name=theme-color]')?.setAttribute('content', getComputedStyle(document.documentElement).getPropertyValue('--t-canvas').trim() || '#ffffff'); } }"
     x-init="applyTheme(saved); $watch('preview', value => applyTheme(value || saved))"
     @mouseleave="preview = null"
     @pageshow.window="applyTheme(saved)">
    <x-page-heading title="Themes" description="Pick the look of your dashboard. Your theme belongs to your account, so it never changes what other chamber users see.">
        <x-slot:action>
            <span class="current-theme">
                <span class="current-theme-dots" aria-hidden="true">@foreach($currentTheme['dots'] as $dot)<span style="background: {{ $dot }}"></span>@endforeach</span>
                <span>Current<span class="sr-only"> theme:</span> <strong>{{ $currentTheme['name'] }}</strong></span>
            </span>
        </x-slot:action>
    </x-page-heading>

    <p class="theme-hint">Hover or focus a theme to try it on this page. Nothing is saved until you choose a card.</p>

    <form method="POST" action="{{ route('themes.update') }}" @submit="submitting = true" :aria-busy="submitting.toString()">
        @csrf
        <div class="theme-gallery">
            @foreach($themes as $key => $theme)
                @php($isCurrent = $key === $current)
                <button type="submit"
                        name="theme"
                        value="{{ $key }}"
                        class="theme-card"
                        aria-pressed="{{ $isCurrent ? 'true' : 'false' }}"
                        aria-label="Use the {{ $theme['name'] }} theme ({{ $theme['mode'] }}): {{ $theme['description'] }}"
                        @mouseenter="preview = '{{ $key }}'"
                        @focus="preview = '{{ $key }}'"
                        @mouseleave="preview = null"
                        @blur="preview = null"
                        @click="saved = '{{ $key }}'; submitting = true">
                    @if($isCurrent)
                        <span class="theme-card-check" title="Current theme"><x-icon name="check" size="13" /><span class="sr-only">Current theme</span></span>
                    @endif
                    @include('themes.preview', ['theme' => $key])
                    <span class="theme-card-body">
                        <span class="theme-card-title">
                            <strong>{{ $theme['name'] }}</strong>
                            <span class="theme-dots" aria-hidden="true">@foreach($theme['dots'] as $dot)<span style="background: {{ $dot }}"></span>@endforeach</span>
                        </span>
                        <span class="theme-card-tone">{{ $theme['tone'] }}</span>
                        <span class="theme-card-description">{{ $theme['description'] }}</span>
                        <span class="theme-card-meta">
                            <span class="theme-mode"><x-icon name="{{ $theme['mode'] === 'Dark' ? 'moon' : 'sun' }}" size="12" />{{ $theme['mode'] }}</span>
                            @if($isCurrent)<span class="theme-current">Current</span>@endif
                            <span class="theme-applying" role="status" aria-live="polite" x-show="submitting && saved === '{{ $key }}'" x-cloak><x-spinner class="spinner-inline" />Applying…</span>
                        </span>
                    </span>
                </button>
            @endforeach
        </div>
    </form>

    <p class="theme-footnote">Themes are adapted from the Apple, Cal.com, Intercom, Linear, Mintlify, Notion, Resend, SAP, Starbucks, and Zapier design analyses. Your choice is saved to your account and applies on every device you sign in from.</p>
</div>
@endsection
