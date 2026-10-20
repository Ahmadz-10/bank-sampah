@extends('layouts.app')

@section('title', $user->exists ? __('Edit user') : __('Add user'))

@section('content')
    @php($isSelf = $user->exists && $user->is(auth()->user()))

    <x-page-header :title="$user->exists ? 'Edit user' : 'Add user'" />

    <form method="POST" novalidate class="card card-body" style="max-width: 720px;"
          action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-md-6"><x-form.input name="name" label="Full name" :value="$user->name" required /></div>
            <div class="col-md-6"><x-form.input name="email" label="Email" type="email" :value="$user->email" required /></div>
        </div>

        <x-form.input name="phone" label="Phone" :value="$user->phone" />

        <div class="row">
            <div class="col-md-6">
                <x-form.select name="role_id" label="Role" :options="$roleOptions" :value="$user->role_id"
                               placeholder="Select a role" :required="! $isSelf" :disabled="$isSelf"
                               :help="$isSelf ? 'You cannot change your own role.' : null" />
            </div>
            <div class="col-md-6 pt-md-4">
                <x-form.checkbox name="is_active" label="Active" :checked="$user->is_active" :disabled="$isSelf"
                                 :help="$isSelf ? 'You cannot deactivate your own account.' : null" />
            </div>
        </div>

        <hr>
        <h2 class="h6">{{ $user->exists ? __('Change password') : __('Password') }}</h2>
        @if ($user->exists)
            <p class="text-body-secondary small">{{ __('Leave blank to keep the current password.') }}</p>
        @endif

        <div class="row">
            <div class="col-md-6">
                <x-form.input name="password" label="Password" type="password" :required="! $user->exists"
                              autocomplete="new-password" help="At least 8 characters." />
            </div>
            <div class="col-md-6">
                <x-form.input name="password_confirmation" label="Confirm password" type="password"
                              :required="! $user->exists" autocomplete="new-password" />
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
