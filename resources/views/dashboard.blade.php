@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
    <h1 class="h4 mb-1">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
    <p class="text-body-secondary">
        {{ __('You are signed in as :role.', ['role' => auth()->user()->role?->label]) }}
    </p>

    <div class="card">
        <div class="card-body text-body-secondary">
            {{ __('Dashboard statistics will be added in Phase 6.') }}
        </div>
    </div>
@endsection
