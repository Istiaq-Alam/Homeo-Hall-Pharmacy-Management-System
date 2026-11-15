@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('pagination.navigation') }}">
        @foreach ($paginator->linkCollection() as $link)
            @php($label = ctype_digit((string) $link['label']) ? localized_number($link['label']) : $link['label'])
            @if ($link['active'])
                <span aria-current="page"><span>{{ $label }}</span></span>
            @elseif ($link['url'])
                <a href="{{ $link['url'] }}" @if($loop->first) rel="prev" @elseif($loop->last) rel="next" @endif @if(! empty($link['page'])) aria-label="{{ __('pagination.go_to_page', ['page' => localized_number($link['page'])]) }}" @endif>{!! $label !!}</a>
            @elseif ($link['label'] === '...')
                <span aria-hidden="true">…</span>
            @else
                <span aria-disabled="true">{!! $label !!}</span>
            @endif
        @endforeach
    </nav>
@endif
