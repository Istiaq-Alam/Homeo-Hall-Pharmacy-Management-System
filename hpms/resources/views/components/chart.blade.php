@props([
    'id',
    'type' => 'line',
    'caption',
    'labels' => [],
    'series' => [],
    'container' => 'chart-box',
])

{{-- The canvas is the visual rendering; the visually hidden table is its text
     alternative for screen readers and for anyone reading with CSS off. --}}
<div class="{{ $container }}">
    <canvas data-chart="{{ $id }}" data-type="{{ $type }}" role="img" aria-label="{{ $caption }}"></canvas>
    <table class="sr-only">
        <caption>{{ $caption }}</caption>
        <thead>
            <tr>
                <th scope="col">{{ __('common.period') }}</th>
                @foreach($series as $label => $values)
                    <th scope="col">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($labels as $index => $label)
                <tr>
                    <th scope="row">{{ $label }}</th>
                    @foreach($series as $values)
                        <td>{{ localized_number($values[$index] ?? 0, 2) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
