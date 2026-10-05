@extends('layouts.guest')

@section('title', '403')

@section('content')
    <div class="text-center">
        <div class="display-4 fw-bold text-body-secondary">403</div>
        <p class="mb-3">{{ __('You do not have permission to access this page.') }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary">{{ __('Back to dashboard') }}</a>

        {{-- Lets a user with no role (or no access) sign out instead of getting stuck. --}}
        @auth
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-link btn-sm">{{ __('Log out') }}</button>
            </form>
        @endauth
    </div>
@endsection
