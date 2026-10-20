@props(['title'])

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">{{ __($title) }}</h1>
    <div class="d-flex gap-2">{{ $slot }}</div>
</div>
