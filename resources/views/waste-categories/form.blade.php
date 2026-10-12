@extends('layouts.app')

@section('title', $category->exists ? __('Edit category') : __('Add category'))

@section('content')
    <x-page-header :title="$category->exists ? 'Edit category' : 'Add category'" />

    <form method="POST" novalidate class="card card-body" style="max-width: 720px;"
          action="{{ $category->exists ? route('waste-categories.update', $category) : route('waste-categories.store') }}">
        @csrf
        @if ($category->exists)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-md-4">
                <x-form.input name="code" label="Code" :value="$category->code" required maxlength="20"
                              help="Short unique code, e.g. PLS." />
            </div>
            <div class="col-md-8"><x-form.input name="name" label="Name" :value="$category->name" required /></div>
        </div>

        <x-form.select name="unit" label="Unit" :options="\App\Models\WasteCategory::UNITS" :value="$category->unit" required />
        <x-form.textarea name="description" label="Description" :value="$category->description" rows="2" />

        @unless ($category->exists)
            <hr>
            <h2 class="h6">{{ __('Initial price') }}</h2>
            <p class="text-body-secondary small">{{ __('Later changes are made on the Waste Prices page.') }}</p>

            <div class="row">
                <div class="col-md-4"><x-form.input name="buy_price" label="Buy price" type="number" min="0" step="any" required /></div>
                <div class="col-md-4"><x-form.input name="sell_price" label="Sell price" type="number" min="0" step="any" /></div>
                <div class="col-md-4">
                    <x-form.input name="effective_date" label="Effective date" type="date" :value="today()->format('Y-m-d')" required />
                </div>
            </div>
            <hr>
        @endunless

        <x-form.checkbox name="is_active" label="Active" :checked="$category->is_active"
                         help="Inactive categories cannot be used for new prices or transactions." />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('waste-categories.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
