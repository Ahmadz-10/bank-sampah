{{--
    Shared chart (Chart.js, drawn by resources/js/charts.js). Line, bar and doughnut.

    <x-chart type="line" :title="__('Reduction vs target')" :labels="['Jan', 'Feb']"
             :series="[['label' => __('Reduction'), 'data' => [30, null], 'color' => 'success', 'dashed' => false]]"
             unit="%" :min="0" />

    - type: line (default), bar or doughnut. bar also takes :horizontal="true" for long labels.
    - series: each ['label' => ..., 'data' => [one number per label], 'color' => a Bootstrap theme colour name].
      A doughnut has ONE series whose 'color' is a list, one colour per slice: ['success', 'warning', 'secondary'].
      Colour names (primary, success, warning, danger, info, secondary) follow the light/dark theme.
    - data: null leaves a gap in a line (a month without data) instead of drawing 0.
    - unit is appended to values in axes and tooltips, e.g. '%' or ' kg'.
    - With nothing to draw (no numbers; for a doughnut, all zero) the chart is replaced by a short
      message and Chart.js is not loaded.
--}}
@props([
    'type' => 'line',
    'title',
    'labels' => [],
    'series' => [],
    'unit' => '',
    'min' => 0,
    'max' => null,
    'horizontal' => false,
    'height' => 280,
    'emptyText' => null,
])

@php
    $hasData = $type === 'doughnut'
        ? collect($series)->contains(fn ($line) => collect($line['data'] ?? [])->contains(fn ($value) => (float) $value > 0))
        : collect($series)->contains(fn ($line) => collect($line['data'] ?? [])->contains(fn ($value) => $value !== null));

    $spec = [
        'type' => $type,
        'labels' => array_values($labels),
        'series' => array_values($series),
        'unit' => $unit,
        'min' => $min,
        'max' => $max,
        'horizontal' => (bool) $horizontal,
    ];
@endphp

@if ($hasData)
    @once
        @push('scripts')
            @vite('resources/js/charts.js')
        @endpush
    @endonce

    <div {{ $attributes->class(['position-relative']) }} style="height: {{ (int) $height }}px" data-chart="{{ json_encode($spec) }}">
        <canvas role="img" aria-label="{{ $title }}"></canvas>
    </div>
@else
    <div {{ $attributes->class(['text-body-secondary', 'text-center', 'py-5']) }}>{{ $emptyText ?? __('No data to chart yet.') }}</div>
@endif
