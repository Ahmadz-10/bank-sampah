@extends('layouts.app')

@section('title', __('Waste Reduction'))

@section('content')
    <x-page-header title="Waste Reduction">
        <a href="{{ route('waste-weighings.index', ['year' => $year]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Weighing Sheet') }}</a>
    </x-page-header>

    <form method="GET" action="{{ route('waste-reductions.index') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="year" class="form-select" aria-label="{{ __('Year') }}" onchange="this.form.submit()">
                @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
            </select>
        </div>
        <noscript><div class="col-auto"><button class="btn btn-outline-secondary">{{ __('Filter') }}</button></div></noscript>
    </form>

    <p class="text-body-secondary small">
        {{ __('Figures are calculated from the monthly weighing sheet: reduction = diverted waste / total waste generated.') }}
    </p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead>
                    <tr>
                        <th>{{ __('Month') }}</th>
                        <th class="text-end">{{ __('RW reported') }}</th>
                        <th class="text-end">{{ __('Total generated (kg)') }}</th>
                        <th class="text-end">{{ __('Diverted (kg)') }}</th>
                        <th class="text-end">{{ __('To TPS (kg)') }}</th>
                        <th class="text-end">{{ __('Reduction') }}</th>
                        <th class="text-end">{{ __('Target') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end" title="{{ __('Recorded through bank sampah deposits (reference only)') }}">{{ __('Deposits (kg)') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($rec = $row['record'])
                        <tr class="{{ $rec ? '' : 'text-body-secondary' }}">
                            <td>{{ __(\Illuminate\Support\Carbon::create(2000, $row['month'], 1)->format('F')) }}</td>
                            <td class="text-end">{{ $row['rw_reported'] }}</td>
                            @if ($rec)
                                <td class="text-end">{{ \App\Support\Format::weight($rec->total_waste_generated) }}</td>
                                <td class="text-end">{{ \App\Support\Format::weight($rec->waste_diverted) }}</td>
                                <td class="text-end">{{ \App\Support\Format::weight($rec->waste_to_tps) }}</td>
                                <td class="text-end fw-semibold">{{ \App\Support\Format::weight($rec->reduction_percentage) }}%</td>
                                <td class="text-end">{{ \App\Support\Format::weight($rec->target_percentage) }}%</td>
                                <td><span class="badge text-bg-{{ $rec->metTarget() ? 'success' : 'warning' }}">{{ $rec->metTarget() ? __('Target met') : __('Below target') }}</span></td>
                            @else
                                <td colspan="5" class="text-center">{{ __('No weighing recorded') }}</td>
                                <td></td>
                            @endif
                            <td class="text-end">{{ $row['deposited_kg'] > 0 ? \App\Support\Format::weight($row['deposited_kg']) : '-' }}</td>
                            <td class="text-end">
                                @if ($rec)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('waste-reductions.edit', $rec) }}">{{ __('Set target') }}</a>
                                @else
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('waste-weighings.index', ['year' => $year, 'month' => $row['month']]) }}">{{ __('Enter') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="2">{{ __('Total') }} {{ $year }}</td>
                        <td class="text-end">{{ \App\Support\Format::weight($totals['generated']) }}</td>
                        <td class="text-end">{{ \App\Support\Format::weight($totals['diverted']) }}</td>
                        <td class="text-end">{{ \App\Support\Format::weight($totals['to_tps']) }}</td>
                        <td class="text-end">{{ \App\Support\Format::weight($totals['percentage']) }}%</td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
