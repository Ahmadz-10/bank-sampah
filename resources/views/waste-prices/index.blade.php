@extends('layouts.app')

@section('title', __('Waste Prices'))

@section('content')
    <x-page-header title="Waste Prices">
        <a href="{{ route('waste-prices.create', request()->filled('category') ? ['category' => request('category')] : []) }}"
           class="btn btn-primary btn-sm">{{ __('Add price') }}</a>
    </x-page-header>

    <form method="GET" action="{{ route('waste-prices.index') }}" class="row g-2 mb-3">
        <div class="col-md-5">
            <select name="category" class="form-select">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>
                        {{ $category->code }} - {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            @if (request()->filled('category'))
                <a href="{{ route('waste-prices.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th class="text-end">{{ __('Buy price') }}</th>
                        <th class="text-end">{{ __('Sell price') }}</th>
                        <th>{{ __('Effective date') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prices as $price)
                        <tr>
                            <td>{{ $price->category->code }} <span class="text-body-secondary">{{ $price->category->name }}</span></td>
                            <td class="text-end text-nowrap">Rp {{ number_format((float) $price->buy_price, 0, ',', '.') }}</td>
                            <td class="text-end text-nowrap">
                                {{ $price->sell_price !== null ? 'Rp '.number_format((float) $price->sell_price, 0, ',', '.') : '-' }}
                            </td>
                            <td class="text-nowrap">{{ $price->effective_date->format('d/m/Y') }}</td>
                            <td>
                                @if ($price->effective_date->isFuture())
                                    <span class="badge text-bg-info">{{ __('Upcoming') }}</span>
                                @elseif (in_array($price->id, $currentIds, true))
                                    <span class="badge text-bg-success">{{ __('Current') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Past') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('waste-prices.edit', $price) }}">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('waste-prices.destroy', $price) }}" class="d-inline"
                                      data-confirm="{{ __('Delete this price?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $prices->links('pagination::bootstrap-5') }}</div>
@endsection
