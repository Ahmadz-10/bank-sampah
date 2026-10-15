@extends('layouts.app')

@section('title', __('New deposit'))

@section('content')
    <x-page-header title="New deposit" />

    <form method="POST" action="{{ route('waste-deposits.store') }}" novalidate class="card card-body">
        @csrf

        <div class="row">
            <div class="col-md-6">
                <x-form.select name="customer_id" label="Customer" :options="$customers" :value="$customerId"
                               placeholder="Select a customer" required />
            </div>
            <div class="col-md-3">
                <x-form.input name="deposit_date" label="Deposit date" type="date"
                              :value="today()->format('Y-m-d')" :max="today()->format('Y-m-d')" required />
            </div>
        </div>

        <x-transaction-lines mode="deposit" :categories="$categories" :prices="$priceTable"
                             :initial="$initial" date-input="deposit_date" />

        <x-form.textarea name="notes" label="Notes" rows="2" />

        <p class="text-body-secondary small">
            {{ __('Prices come from the price list on the deposit date. Amounts shown here are an estimate; the final amounts are calculated when you save.') }}
        </p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save deposit') }}</button>
            <a href="{{ route('waste-deposits.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
