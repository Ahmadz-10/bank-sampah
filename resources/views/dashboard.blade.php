@extends('layouts.app')

@section('title', __('Dashboard'))

@php
    use App\Support\Format;

    $d = $dashboard;
    $show = $d['show'];
    $user = auth()->user();
    $count = fn (int $n) => number_format($n, 0, ',', '.');

    $monthRange = ['from' => $d['month_start'], 'to' => $d['month_end']];   // same range as the Deposits / Sales filters
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-1">{{ __('Welcome, :name', ['name' => $user->name]) }}</h1>
        <p class="text-body-secondary mb-0">
            {{ __('You are signed in as :role.', ['role' => $user->role?->label]) }}
            {{ __('Figures for :month.', ['month' => $d['month_label']]) }}
        </p>
    </div>

    {{-- Number cards: one per block the role may see --}}
    <div class="row row-cols-2 row-cols-lg-3 g-3 mb-4">
        @if ($show['households'])
            <x-stat-card :label="__('Households')" :value="$count($d['households']['total'])"
                         :hint="__('Sorting: :count', ['count' => $count($d['households']['sorting'])])"
                         :href="$user->can('manage-households') ? route('households.index') : null" />
        @endif

        @if ($show['customers'])
            <x-stat-card :label="__('Active customers')" :value="$count($d['customers']['active'])"
                         :hint="__(':count registered', ['count' => $count($d['customers']['total'])])"
                         :href="$user->can('manage-customers') ? route('customers.index') : null" />
        @endif

        @if ($show['transactions'])
            <x-stat-card :label="__('Deposits this month')" :value="Format::weight($d['deposits']['weight']).' kg'"
                         :hint="__(':count deposits, :amount', ['count' => $count($d['deposits']['count']), 'amount' => Format::money($d['deposits']['amount'])])"
                         :href="$user->can('manage-transactions') ? route('waste-deposits.index', $monthRange) : null" />

            <x-stat-card :label="__('Sales this month')" :value="Format::money($d['sales']['amount'])"
                         :hint="__(':count sales, :weight kg', ['count' => $count($d['sales']['count']), 'weight' => Format::weight($d['sales']['weight'])])"
                         :href="$user->can('manage-transactions') ? route('waste-sales.index', $monthRange) : null" />

            <x-stat-card :label="__('Stock in hand')" :value="Format::weight($d['stock_kg']).' kg'"
                         :hint="__('Categories counted in kg')"
                         :href="$user->can('manage-transactions') ? route('waste-sales.index') : null" />
        @endif

        @if ($show['surveys'])
            <x-stat-card :label="__('Pending surveys')" :value="$count($d['pending_surveys'])"
                         :hint="__('Waiting for validation')"
                         :href="route('household-surveys.index', ['status' => 'pending'])" />
        @endif
    </div>

    {{-- Reduction vs target for the year --}}
    @if ($show['reduction'])
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ __('Reduction vs target') }} {{ $d['year'] }}</h2>
                <x-chart type="line" :title="__('Reduction vs target').' '.$d['year']"
                         :labels="$d['reduction_chart']['labels']" :series="$d['reduction_chart']['series']"
                         unit="%" :min="0" :height="300" />
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        @if ($show['households'])
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Sorting households') }}</h2>
                        <x-chart type="doughnut" :title="__('Sorting households')"
                                 :labels="[__('Sorting'), __('Not sorting'), __('Not surveyed')]"
                                 :series="[['label' => __('Households'), 'color' => ['success', 'warning', 'secondary'], 'data' => [$d['households']['sorting'], $d['households']['not_sorting'], $d['households']['unknown']]]]"
                                 :height="260" :empty-text="__('No households recorded yet.')" />
                    </div>
                </div>
            </div>
        @endif

        @if ($show['transactions'])
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Waste deposited by category') }} <span class="text-body-secondary fw-normal">({{ $d['month_label'] }})</span></h2>
                        <x-chart type="bar" :horizontal="true" :title="__('Waste deposited by category')"
                                 :labels="collect($d['categories'])->pluck('name')->all()"
                                 :series="[['label' => __('Weight'), 'color' => 'primary', 'data' => collect($d['categories'])->pluck('kg')->all()]]"
                                 unit=" kg" :min="0" :height="max(200, 60 + 38 * count($d['categories']))"
                                 :empty-text="__('No deposits this month.')" />
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Latest rows: only for roles that can open the Deposits / Sales pages --}}
    @if ($show['recent'])
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0">{{ __('Recent deposits') }}</h2>
                        <a href="{{ route('waste-deposits.index') }}" class="small">{{ __('View all') }}</a>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($d['recent_deposits'] as $deposit)
                            <a href="{{ route('waste-deposits.show', $deposit) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-3">
                                <span class="text-truncate">
                                    <span class="fw-semibold">{{ $deposit->deposit_number }}</span><br>
                                    <small class="text-body-secondary">{{ $deposit->customer?->name }} &middot; {{ $deposit->deposit_date->format('d/m/Y') }}</small>
                                </span>
                                <span class="text-end text-nowrap">
                                    {{ Format::money($deposit->total_amount) }}<br>
                                    <small class="text-body-secondary">{{ Format::weight($deposit->total_weight) }} kg</small>
                                </span>
                            </a>
                        @empty
                            <div class="list-group-item text-body-secondary text-center py-4">{{ __('No deposits yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0">{{ __('Recent sales') }}</h2>
                        <a href="{{ route('waste-sales.index') }}" class="small">{{ __('View all') }}</a>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($d['recent_sales'] as $sale)
                            <a href="{{ route('waste-sales.show', $sale) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-3">
                                <span class="text-truncate">
                                    <span class="fw-semibold">{{ $sale->sale_number }}</span><br>
                                    <small class="text-body-secondary">{{ $sale->buyer_name }} &middot; {{ $sale->sale_date->format('d/m/Y') }}</small>
                                </span>
                                <span class="text-end text-nowrap">
                                    {{ Format::money($sale->total_amount) }}<br>
                                    <small class="text-body-secondary">{{ Format::weight($sale->total_weight) }} kg</small>
                                </span>
                            </a>
                        @empty
                            <div class="list-group-item text-body-secondary text-center py-4">{{ __('No sales yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
