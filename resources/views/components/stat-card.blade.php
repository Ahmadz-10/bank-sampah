{{-- A number card for the dashboard grid. With :href the whole card is a link. --}}
@props(['label', 'value', 'hint' => null, 'href' => null])

<div class="col">
    <div {{ $attributes->class(['card', 'h-100']) }}>
        <div class="card-body">
            <div class="small text-body-secondary">{{ $label }}</div>
            <div class="h4 mb-0 text-break">
                @if ($href)
                    <a href="{{ $href }}" class="text-reset text-decoration-none stretched-link">{{ $value }}</a>
                @else
                    {{ $value }}
                @endif
            </div>
            @if ($hint)
                <div class="small text-body-secondary mt-1">{{ $hint }}</div>
            @endif
        </div>
    </div>
</div>
