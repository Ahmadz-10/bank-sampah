{{-- Driven by config/menu.php. Items show only if the route exists and the user passes the ability. --}}
<ul class="nav nav-pills flex-column gap-1">
    @foreach (config('menu') as $group)
        @php
            $items = collect($group['items'])->filter(
                fn ($item) => Route::has($item['route']) && auth()->user()->can($item['can'])
            );
        @endphp

        @continue($items->isEmpty())

        @if (! empty($group['label']))
            <li class="text-uppercase small text-body-secondary fw-semibold mt-3 mb-1 px-2">{{ __($group['label']) }}</li>
        @endif

        @foreach ($items as $item)
            @php
                $active = request()->routeIs($item['route'], Str::beforeLast($item['route'], '.').'.*');
            @endphp
            <li class="nav-item">
                <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route($item['route']) }}">{{ __($item['label']) }}</a>
            </li>
        @endforeach
    @endforeach
</ul>
