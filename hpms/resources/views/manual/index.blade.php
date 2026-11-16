@extends('layouts.app')
@section('title', __('manual.title'))
@section('content')
<x-page-heading :title="__('manual.title')" :description="__('manual.description')">
    <x-slot:action>
        <div class="heading-action">
            <a class="button button-secondary" href="{{ route('manual.pdf') }}" target="_blank" rel="noopener"><x-icon name="manual" /> {{ __('manual.open_new_tab') }}</a>
            <a class="button button-primary" href="{{ route('manual.download') }}"><x-icon name="download" /> {{ __('manual.download') }}</a>
        </div>
    </x-slot:action>
</x-page-heading>

<section class="manual-viewer" aria-labelledby="manual-viewer-title">
    <div class="manual-toolbar">
        <div class="manual-file">
            <span class="manual-file-icon" aria-hidden="true"><x-icon name="manual" size="20" /></span>
            <span class="manual-file-copy">
                <strong id="manual-viewer-title">{{ $fileName }}</strong>
                <small>{{ __('manual.file_meta', ['size' => localized_number($fileSize, 1), 'date' => localized_date($updatedAt)]) }}</small>
            </span>
        </div>
        <span class="status status-manual">{{ __('manual.language_label') }}</span>
    </div>
    <div class="manual-frame">
        <iframe
            src="{{ route('manual.pdf') }}#view=FitH"
            title="{{ __('manual.viewer_title') }}"
            loading="eager"
            allowfullscreen
        ></iframe>
    </div>
    <p class="manual-fallback">
        {{ __('manual.fallback') }}
        <a href="{{ route('manual.pdf') }}" target="_blank" rel="noopener">{{ __('manual.open_new_tab') }}</a>
    </p>
</section>
@endsection
