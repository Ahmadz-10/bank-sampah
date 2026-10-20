@extends('layouts.app')

@section('title', $household->exists ? __('Edit household') : __('Add household'))

@section('content')
    <x-page-header :title="$household->exists ? 'Edit household' : 'Add household'" />

    <form method="POST" novalidate class="card card-body" style="max-width: 720px;"
          action="{{ $household->exists ? route('households.update', $household) : route('households.store') }}">
        @csrf
        @if ($household->exists)
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">{{ __('Household code') }}</label>
                <input type="text" class="form-control" value="{{ $household->household_code }}" disabled>
            </div>
        @else
            <p class="text-body-secondary small">{{ __('The household code is generated automatically.') }}</p>
        @endif

        <x-form.input name="head_name" label="Head of household" :value="$household->head_name" required />
        <x-form.textarea name="address" label="Address" :value="$household->address" rows="2" required />

        <div class="row">
            <div class="col-md-4"><x-form.input name="rt" label="RT" :value="$household->rt" /></div>
            <div class="col-md-4">
                <x-form.select name="rw_id" label="RW" :options="$rwOptions" :value="$household->rw_id" placeholder="No RW" />
            </div>
            <div class="col-md-4">
                <x-form.input name="member_count" label="Family members" type="number" min="1" :value="$household->member_count" required />
            </div>
        </div>

        <x-form.input name="phone" label="Phone" :value="$household->phone" />

        @if ($household->exists)
            <div class="mb-3">
                <span class="form-label d-block">{{ __('Sorting status') }}</span>
                <span class="badge text-bg-{{ $household->statusBadge() }}">{{ $household->statusLabel() }}</span>
                <div class="form-text">{{ __('Updated automatically from validated household surveys.') }}</div>
            </div>
        @endif

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('households.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
