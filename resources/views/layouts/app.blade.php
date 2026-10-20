<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ auth()->user()?->theme ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-body-tertiary">
    <nav class="navbar bg-body border-bottom sticky-top">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm d-md-none" type="button"
                        data-bs-toggle="collapse" data-bs-target="#sidebar"
                        aria-controls="sidebar" aria-expanded="false" aria-label="{{ __('Toggle menu') }}">&#9776;</button>
                <a class="navbar-brand fw-semibold mb-0" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="text-end lh-sm d-none d-sm-block">
                    <div class="fw-medium">{{ auth()->user()->name }}</div>
                    <small class="text-body-secondary">{{ auth()->user()->role?->label ?? __('No role') }}</small>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Log out') }}</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <aside id="sidebar" class="col-md-3 col-lg-2 collapse d-md-block bg-body border-end p-3" style="min-height: calc(100vh - 57px);">
                @include('layouts.partials.sidebar')
            </aside>

            <main class="col-md-9 col-lg-10 p-4">
                @if (session('status'))
                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    {{-- Any <form data-confirm="..."> asks for confirmation before submitting. --}}
    <script>
        document.addEventListener('submit', function (event) {
            var message = event.target.dataset ? event.target.dataset.confirm : null;
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
