@extends('layouts.app')

@section('title', $customer->customer_code)

@section('content')
    <x-page-header :title="$customer->name">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm">{{ __('Edit') }}</a>
        @can('manage-transactions')
            @if ($customer->is_active)
                <a href="{{ route('waste-deposits.create', ['customer' => $customer->id]) }}" class="btn btn-primary btn-sm">{{ __('New deposit') }}</a>
            @endif
        @endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Customer details') }}</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Customer code') }}</dt>
                        <dd class="col-sm-8">{{ $customer->customer_code }}</dd>
                        <dt class="col-sm-4">{{ __('Household') }}</dt>
                        <dd class="col-sm-8">
                            @if ($customer->household)
                                <a href="{{ route('households.show', $customer->household) }}" class="text-decoration-none">
                                    {{ $customer->household->household_code }} - {{ $customer->household->head_name }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>
                        <dt class="col-sm-4">{{ __('ID number (NIK)') }}</dt>
                        <dd class="col-sm-8">{{ $customer->id_number ?: '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Phone') }}</dt>
                        <dd class="col-sm-8">{{ $customer->phone ?: '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Address') }}</dt>
                        <dd class="col-sm-8">{{ $customer->address ?: '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Joined') }}</dt>
                        <dd class="col-sm-8">{{ $customer->joined_at?->format('d/m/Y') ?? '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Status') }}</dt>
                        <dd class="col-sm-8 mb-0">
                            <span class="badge text-bg-{{ $customer->is_active ? 'success' : 'secondary' }}">
                                {{ $customer->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Deposit summary') }}</div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-secondary">{{ __('Deposits') }}</span><div class="fs-5 fw-semibold">{{ number_format((int) $totals->cnt, 0, ',', '.') }}</div></div>
                    <div class="mb-2"><span class="text-body-secondary">{{ __('Total weight') }}</span><div class="fs-5 fw-semibold">{{ \App\Support\Format::weight($totals->weight) }}</div></div>
                    <div><span class="text-body-secondary">{{ __('Total amount') }}</span><div class="fs-5 fw-semibold">{{ \App\Support\Format::money($totals->amount) }}</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">{{ __('Deposit history') }}</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Date') }}</th>
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
                            <td class="text-end text-nowrap">{{ \App\Support\Format::weight($deposit->total_weight) }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::money($deposit->total_amount) }}</td>
                            <td class="text-end">
                                @can('manage-transactions')
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('waste-deposits.show', $deposit) }}">{{ __('View') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No deposits yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($totals->cnt > $deposits->count())
            <div class="card-footer small text-body-secondary">{{ __('Showing the latest :count deposits.', ['count' => $deposits->count()]) }}</div>
        @endif
    </div>
@endsection
