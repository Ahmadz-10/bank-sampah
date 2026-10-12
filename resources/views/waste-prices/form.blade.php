@extends('layouts.app')

@section('title', $price->exists ? __('Edit price') : __('Add price'))

@section('content')
    <x-page-header :title="$price->exists ? 'Edit price' : 'Add price'" />

    <form method="POST" novalidate class="card card-body" style="max-width: 560px;"
          action="{{ $price->exists ? route('waste-prices.update', $price) : route('waste-prices.store') }}">
        @csrf
        @if ($price->exists)
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">{{ __('Category') }}</label>
                <input type="text" class="form-control" disabled value="{{ $price->category->code }} - {{ $price->category->name }}">
            </div>
        @else
            <x-form.select name="waste_category_id" label="Category" :options="$categoryOptions"
                           :value="$price->waste_category_id" placeholder="Select a category" required />
        @endif

        <div class="row">
            <div class="col-md-6">
                <x-form.input name="buy_price" label="Buy price" type="number" min="0" step="any"
                              :value="$price->buy_price" required help="Paid to the customer per unit." />
            </div>
            <div class="col-md-6">
                <x-form.input name="sell_price" label="Sell price" type="number" min="0" step="any"
                              :value="$price->sell_price" help="Optional. Reference price when selling to a collector." />
            </div>
        </div>

        <x-form.input name="effective_date" label="Effective date" type="date"
                      :value="$price->effective_date?->format('Y-m-d')" required
                      help="The price applies from this date until a newer price takes over." />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('waste-prices.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
