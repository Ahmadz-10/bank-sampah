@extends('layouts.app')

@section('title', $deposit->deposit_number)

@push('styles')
    <style>
        @media print {
            nav.navbar, #sidebar, .no-print, .alert { display: none !important; }
            main { flex: 0 0 100% !important; max-width: 100% !important; }
            body { background: #fff !important; }
        }
    </style>
@endpush

@section('content')
    <x-page-header :title="$deposit->deposit_number">
        <div class="no-print d-flex gap-2">
            <a href="{{ route('waste-deposits.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">{{ __('Print') }}</button>
            @if (! $deposit->trashed())
                @can('correct-transactions')
                    <form method="POST" action="{{ route('waste-deposits.destroy', $deposit) }}" class="d-inline"
                          data-confirm="{{ __('Void this deposit? It will be removed from totals and stock.') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Void') }}</button>
                    </form>
                @endcan
            @endif
        </div>
    </x-page-header>

    @if ($deposit->trashed())
        <div class="alert alert-secondary">
            <strong>{{ __('Voided') }}</strong> &middot; {{ $deposit->deleted_at->format('d/m/Y H:i') }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('Customer') }}</dt>
                <dd class="col-sm-9">
                    @can('manage-customers')
                        <a href="{{ route('customers.show', $deposit->customer) }}" class="text-decoration-none">{{ $deposit->customer->customer_code }} - {{ $deposit->customer->name }}</a>
                    @else
                        {{ $deposit->customer->customer_code }} - {{ $deposit->customer->name }}
                    @endcan
                </dd>
                <dt class="col-sm-3">{{ __('Deposit date') }}</dt>
                <dd class="col-sm-9">{{ $deposit->deposit_date->format('d/m/Y') }}</dd>
                <dt class="col-sm-3">{{ __('Recorded by') }}</dt>
                <dd class="col-sm-9">{{ $deposit->officer?->name ?? '-' }}</dd>
                <dt class="col-sm-3">{{ __('Notes') }}</dt>
                <dd class="col-sm-9 mb-0">{!! nl2br(e($deposit->notes ?: '-')) !!}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th class="text-end">{{ __('Weight') }}</th>
                        <th class="text-end">{{ __('Price per unit') }}</th>
                        <th class="text-end">{{ __('Subtotal') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deposit->details as $line)
                        <tr>
                            <td>{{ $line->category->code }} <span class="text-body-secondary">{{ $line->category->name }}</span></td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::weight($line->weight) }} {{ $line->category->unit }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::money($line->price_per_unit) }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Format::money($line->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>{{ __('Total') }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Format::weight($deposit->total_weight) }}</td>
                        <td></td>
                        <td class="text-end text-nowrap">{{ \App\Support\Format::money($deposit->total_amount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
