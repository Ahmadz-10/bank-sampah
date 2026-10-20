{{--
    Line chart: <x-chart type="line"> with the same props, kept so existing pages keep working.
    See components/chart.blade.php for the full list of props.
--}}
@props([
    'title',
    'labels' => [],
    'series' => [],
    'unit' => '',
    'min' => 0,
    'max' => null,
    'height' => 280,
    'emptyText' => null,
])

<x-chart type="line" :title="$title" :labels="$labels" :series="$series" :unit="$unit" :min="$min" :max="$max" :height="$height" :empty-text="$emptyText" {{ $attributes }} />
