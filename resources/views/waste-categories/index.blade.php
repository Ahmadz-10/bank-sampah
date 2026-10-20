@extends('layouts.app')

@section('title', __('Waste Categories'))

@section('content')
    <x-page-header title="Waste Categories">
        <a href="{{ route('waste-prices.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Price history') }}</a>
        <a href="{{ route('waste-categories.create') }}" class="btn btn-primary btn-sm">{{ __('Add category') }}</a>
    </x-page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th>{{ __('Waste group') }}</th>
                        <th class="text-end">{{ __('Buy price') }}</th>
                        <th class="text-end">{{ __('Sell price') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        @php($current = $category->priceOn())
                        <tr>
                            <td class="text-nowrap">{{ $category->code }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->unit }}</td>
                            <td>{{ __(\App\Models\WasteCategory::GROUPS[$category->waste_group] ?? $category->waste_group) }}</td>
                            <td class="text-end text-nowrap">
                                {{ $current ? 'Rp '.number_format((float) $current->buy_price, 0, ',', '.') : '-' }}
                            </td>
                            <td class="text-end text-nowrap">
                                {{ $current && $current->sell_price !== null ? 'Rp '.number_format((float) $current->sell_price, 0, ',', '.') : '-' }}
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $category->is_active ? 'success' : 'secondary' }}">
                                    {{ $category->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('waste-prices.index', ['category' => $category->id]) }}">{{ __('Prices') }}</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('waste-categories.edit', $category) }}">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
