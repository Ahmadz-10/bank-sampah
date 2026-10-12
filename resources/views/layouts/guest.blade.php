<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary d-flex align-items-center min-vh-100">
    <main class="container py-4" style="max-width: 440px;">
        <h1 class="h4 fw-semibold text-center mb-4">{{ config('app.name') }}</h1>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
