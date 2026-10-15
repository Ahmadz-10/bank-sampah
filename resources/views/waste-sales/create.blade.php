@extends('layouts.app')

@section('title', __('New sale'))

@section('content')
    <x-page-header title="New sale" />

    <form method="POST" action="{{ route('waste-sales.store') }}" novalidate class="card card-body">
        @csrf

        <div class="row">
            <div class="col-md-5"><x-form.input name="buyer_name" label="Buyer" required /></div>
            <div class="col-md-4"><x-form.input name="buyer_contact" label="Buyer contact" /></div>
            <div class="col-md-3">
                <x-form.input name="sale_date" label="Sale date" type="date"
                              :value="today()->format('Y-m-d')" :max="today()->format('Y-m-d')" required />
            </div>
        </div>

        <x-transaction-lines mode="sale" :categories="$categories" :prices="$priceTable" :stock="$stock"
                             :initial="$initial" date-input="sale_date" />

        <x-form.textarea name="notes" label="Notes" rows="2" />

        <p class="text-body-secondary small">
            {{ __('The selling price is suggested from the price list; change it to the agreed price. A sale cannot exceed the stock in hand.') }}
        </p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save sale') }}</button>
            <a href="{{ route('waste-sales.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
