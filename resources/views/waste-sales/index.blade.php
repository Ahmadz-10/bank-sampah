@extends('layouts.app')

@section('title', __('Sales'))

@section('content')
    <x-page-header title="Sales">
        <a href="{{ route('waste-sales.create') }}" class="btn btn-primary btn-sm">{{ __('New sale') }}</a>
    </x-page-header>

    <div class="card mb-3">
        <div class="card-header fw-semibold">{{ __('Current stock') }}</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th class="text-end">{{ __('In stock') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockRows as $row)
                        <tr>
                            <td>{{ $row['category']->code }} <span class="text-body-secondary">{{ $row['category']->name }}</span>
                                @unless ($row['category']->is_active)
                                    <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                @endunless
                            </td>
                            <td class="text-end text-nowrap {{ $row['level'] < 0 ? 'text-danger fw-semibold' : '' }}">
                                {{ \App\Support\Format::weight($row['level']) }} {{ $row['category']->unit }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-body-secondary">{{ __('Stock = weight deposited minus weight sold. Voided records are ignored.') }}</div>
    </div>

    <form method="GET" action="{{ route('waste-sales.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $term }}" class="form-control" placeholder="{{ __('Search number or buyer') }}">
        </div>
        <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control" title="{{ __('From date') }}"></div>
        <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control" title="{{ __('To date') }}"></div>
        @can('correct-transactions')
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">{{ __('Active') }}</option>
                    <option value="voided" @selected(request('status') === 'voided')>{{ __('Voided') }}</option>
                </select>
            </div>
        @endcan
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            @if ($term !== '' || request()->filled('from') || request()->filled('to') || $voided)
                <a href="{{ route('waste-sales.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-sm-4"><div class="card card-body py-2"><div class="small text-body-secondary">{{ __('Transactions') }}</div><div class="fs-5 fw-semibold">{{ number_format((int) $totals->cnt, 0, ',', '.') }}</div></div></div>
        <div class="col-sm-4"><div class="card card-body py-2"><div class="small text-body-secondary">{{ __('Total weight') }}</div><div class="fs-5 fw-semibold">{{ \App\Support\Format::weight($totals->weight) }}</div></div></div>
        <div class="col-sm-4"><div class="card card-body py-2"><div class="small text-body-secondary">{{ __('Total amount') }}</div><div class="fs-5 fw-semibold">{{ \App\Support\Format::money($totals->amount) }}</div></div></div>
    </div>

    @if ($voided)
        <div class="alert alert-secondary py-2">{{ __('Showing voided sales.') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Buyer') }}</th>
                        <th class="text-end">{{ __('Weight') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="text-nowrap">{{ $sale->sale_number }}</td>
                            <td class="text-nowrap">{{ $sale->sale_date->format('d/m/Y') }}</td>
                            <td>{{ $sale->buyer_name }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::weight($sale->total_weight) }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::money($sale->total_amount) }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('waste-sales.show', $sale) }}">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sales->links('pagination::bootstrap-5') }}</div>
@endsection
