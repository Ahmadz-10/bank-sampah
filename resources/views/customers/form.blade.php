@extends('layouts.app')

@section('title', $customer->exists ? __('Edit customer') : __('Add customer'))

@section('content')
    <x-page-header :title="$customer->exists ? 'Edit customer' : 'Add customer'" />

    <form method="POST" novalidate class="card card-body" style="max-width: 720px;"
          action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if ($customer->exists)
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">{{ __('Customer code') }}</label>
                <input type="text" class="form-control" value="{{ $customer->customer_code }}" disabled>
            </div>
        @else
            <p class="text-body-secondary small">{{ __('The customer code is generated automatically.') }}</p>
        @endif

        <x-form.input name="name" label="Full name" :value="$customer->name" required />

        <x-form.select name="household_id" label="Household" :options="$householdOptions"
                       :value="$customer->household_id" placeholder="No household"
                       help="Optional. Link this customer to a registered household." />

        <div class="row">
            <div class="col-md-6">
                <x-form.input name="id_number" label="ID number (NIK)" :value="$customer->id_number"
                              maxlength="16" inputmode="numeric" help="Optional. 16 digits." />
            </div>
            <div class="col-md-6"><x-form.input name="phone" label="Phone" :value="$customer->phone" /></div>
        </div>

        <x-form.textarea name="address" label="Address" :value="$customer->address" rows="2" />

        <x-form.input name="joined_at" label="Joined date" type="date"
                      :value="$customer->exists ? $customer->joined_at?->format('Y-m-d') : today()->format('Y-m-d')" />

        <x-form.checkbox name="is_active" label="Active" :checked="$customer->is_active" />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
