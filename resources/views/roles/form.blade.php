@extends('layouts.app')

@section('title', __('Edit role'))

@section('content')
    <x-page-header title="Edit role" />

    <form method="POST" action="{{ route('roles.update', $role) }}" novalidate class="card card-body" style="max-width: 640px;">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label">{{ __('Identifier') }}</label>
            <input type="text" class="form-control" value="{{ $role->name }}" disabled>
            <div class="form-text">{{ __('The identifier is fixed because permissions refer to it.') }}</div>
        </div>

        <x-form.input name="label" label="Role" :value="$role->label" required />
        <x-form.textarea name="description" label="Description" :value="$role->description" rows="2" />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
