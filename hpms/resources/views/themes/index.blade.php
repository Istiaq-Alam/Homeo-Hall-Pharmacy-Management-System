@extends('layouts.app')
@section('title', __('themes.title'))
@section('content')
@php($currentTheme = $themes[$current])
<div class="theme-page"
     x-data="{ preview: null, saved: @js($current), submitting: false, applyTheme(theme) { document.documentElement.dataset.theme = theme; document.querySelector('meta[name=theme-color]')?.setAttribute('content', getComputedStyle(document.documentElement).getPropertyValue('--t-canvas').trim() || '#ffffff'); } }"
     x-init="applyTheme(saved); $watch('preview', value => applyTheme(value || saved))"
     @mouseleave="preview = null"
     @pageshow.window="applyTheme(saved)">
    <x-page-heading :title="__('themes.title')" :description="__('themes.description')">
        <x-slot:action>
            <span class="current-theme">
                <span class="current-theme-dots" aria-hidden="true">@foreach($currentTheme['dots'] as $dot)<span style="background: {{ $dot }}"></span>@endforeach</span>
                <span>{{ __('themes.current') }}<span class="sr-only">: </span> <strong>{{ $currentTheme['name'] }}</strong></span>
            </span>
        </x-slot:action>
    </x-page-heading>

    <p class="theme-hint">{{ __('themes.hover_hint') }}</p>

    <form method="POST" action="{{ route('themes.update') }}" @submit="submitting = true" :aria-busy="submitting.toString()">
        @csrf
        <div class="theme-gallery">
            @foreach($themes as $key => $theme)
                @php($isCurrent = $key === $current)
                @php($mode = __('themes.'.strtolower($theme['mode'])))
                <button type="submit"
                        name="theme"
                        value="{{ $key }}"
                        class="theme-card"
                        aria-pressed="{{ $isCurrent ? 'true' : 'false' }}"
                        aria-label="{{ __('themes.use_theme', ['name' => $theme['name'], 'mode' => $mode, 'description' => __('themes.items.'.$key.'.description')]) }}"
                        @mouseenter="preview = '{{ $key }}'"
                        @focus="preview = '{{ $key }}'"
                        @mouseleave="preview = null"
                        @blur="preview = null"
                        @click="saved = '{{ $key }}'; submitting = true">
                    @if($isCurrent)
                        <span class="theme-card-check" title="{{ __('themes.current_theme') }}"><x-icon name="check" size="13" /><span class="sr-only">{{ __('themes.current_theme') }}</span></span>
                    @endif
                    @include('themes.preview', ['theme' => $key])
                    <span class="theme-card-body">
                        <span class="theme-card-title">
                            <strong>{{ $theme['name'] }}</strong>
                            <span class="theme-dots" aria-hidden="true">@foreach($theme['dots'] as $dot)<span style="background: {{ $dot }}"></span>@endforeach</span>
                        </span>
                        <span class="theme-card-tone">{{ __('themes.items.'.$key.'.tone') }}</span>
                        <span class="theme-card-description">{{ __('themes.items.'.$key.'.description') }}</span>
                        <span class="theme-card-meta">
                            <span class="theme-mode"><x-icon name="{{ $theme['mode'] === 'Dark' ? 'moon' : 'sun' }}" size="12" />{{ $mode }}</span>
                            @if($isCurrent)<span class="theme-current">{{ __('themes.current') }}</span>@endif
                            <span class="theme-applying" role="status" aria-live="polite" x-show="submitting && saved === '{{ $key }}'" x-cloak><x-spinner class="spinner-inline" />{{ __('themes.applying') }}</span>
                        </span>
                    </span>
                </button>
            @endforeach
        </div>
    </form>

    <p class="theme-footnote">{{ __('themes.footnote') }}</p>
</div>
@endsection
