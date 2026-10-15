@extends('layouts.app')

@section('title', __('Deposits'))

@section('content')
    <x-page-header title="Deposits">
        <a href="{{ route('waste-deposits.create') }}" class="btn btn-primary btn-sm">{{ __('New deposit') }}</a>
    </x-page-header>

    <form method="GET" action="{{ route('waste-deposits.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $term }}" class="form-control" placeholder="{{ __('Search number or customer') }}">
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
                <a href="{{ route('waste-deposits.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-sm-4"><div class="card card-body py-2"><div class="small text-body-secondary">{{ __('Transactions') }}</div><div class="fs-5 fw-semibold">{{ number_format((int) $totals->cnt, 0, ',', '.') }}</div></div></div>
        <div class="col-sm-4"><div class="card card-body py-2"><div class="small text-body-secondary">{{ __('Total weight') }}</div><div class="fs-5 fw-semibold">{{ \App\Support\Format::weight($totals->weight) }}</div></div></div>
        <div class="col-sm-4"><div class="card card-body py-2"><div class="small text-body-secondary">{{ __('Total amount') }}</div><div class="fs-5 fw-semibold">{{ \App\Support\Format::money($totals->amount) }}</div></div></div>
    </div>

    @if ($voided)
        <div class="alert alert-secondary py-2">{{ __('Showing voided deposits.') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th class="text-end">{{ __('Weight') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deposits as $deposit)
                        <tr>
                            <td class="text-nowrap">{{ $deposit->deposit_number }}</td>
                            <td class="text-nowrap">{{ $deposit->deposit_date->format('d/m/Y') }}</td>
                            <td>{{ $deposit->customer->customer_code }} <span class="text-body-secondary">{{ $deposit->customer->name }}</span></td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::weight($deposit->total_weight) }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::money($deposit->total_amount) }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('waste-deposits.show', $deposit) }}">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $deposits->links('pagination::bootstrap-5') }}</div>
@endsection
