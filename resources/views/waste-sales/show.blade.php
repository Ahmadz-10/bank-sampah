@extends('layouts.app')

@section('title', $sale->sale_number)

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
    <x-page-header :title="$sale->sale_number">
        <div class="no-print d-flex gap-2">
            <a href="{{ route('waste-sales.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">{{ __('Print') }}</button>
            @if (! $sale->trashed())
                @can('correct-transactions')
                    <form method="POST" action="{{ route('waste-sales.destroy', $sale) }}" class="d-inline"
                          data-confirm="{{ __('Void this sale? The weight will return to stock.') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Void') }}</button>
                    </form>
                @endcan
            @endif
        </div>
    </x-page-header>

    @if ($sale->trashed())
        <div class="alert alert-secondary">
            <strong>{{ __('Voided') }}</strong> &middot; {{ $sale->deleted_at->format('d/m/Y H:i') }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('Buyer') }}</dt>
                <dd class="col-sm-9">{{ $sale->buyer_name }}</dd>
                <dt class="col-sm-3">{{ __('Buyer contact') }}</dt>
                <dd class="col-sm-9">{{ $sale->buyer_contact ?: '-' }}</dd>
                <dt class="col-sm-3">{{ __('Sale date') }}</dt>
                <dd class="col-sm-9">{{ $sale->sale_date->format('d/m/Y') }}</dd>
                <dt class="col-sm-3">{{ __('Recorded by') }}</dt>
                <dd class="col-sm-9">{{ $sale->officer?->name ?? '-' }}</dd>
                <dt class="col-sm-3">{{ __('Notes') }}</dt>
                <dd class="col-sm-9 mb-0">{!! nl2br(e($sale->notes ?: '-')) !!}</dd>
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
                    @foreach ($sale->details as $line)
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
                        <td class="text-end text-nowrap">{{ \App\Support\Format::weight($sale->total_weight) }}</td>
                        <td></td>
                        <td class="text-end text-nowrap">{{ \App\Support\Format::money($sale->total_amount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
