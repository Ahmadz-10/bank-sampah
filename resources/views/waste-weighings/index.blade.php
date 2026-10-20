@extends('layouts.app')

@section('title', __('Weighing Sheet'))

@php
    // Show stored numbers without trailing zeros; blank when the RW has no report yet.
    $num = fn ($v) => $v === null ? '' : (string) (float) $v;
    $cols = ['organic_kg_per_day', 'inorganic_kg_per_day', 'b3_kg_per_day', 'residue_kg_per_day'];
    $counts = ['carts', 'collectors', 'pickup_frequency'];
@endphp

@section('content')
    <x-page-header title="Weighing Sheet">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('waste-weighings.index', ['year' => $prev->year, 'month' => $prev->month]) }}">&laquo; {{ __($prev->format('F')) }}</a>
        <span class="btn btn-sm disabled fw-semibold">{{ $monthName }}</span>
        @if ($next->lte(today()))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('waste-weighings.index', ['year' => $next->year, 'month' => $next->month]) }}">{{ __($next->format('F')) }} &raquo;</a>
        @endif
    </x-page-header>

    <p class="text-body-secondary small">
        {{ __('Enter the average kg per day for each RW. Totals are calculated. Leave a row empty if the RW did not report this month.') }}
    </p>

    @if ($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0">
            @foreach (collect($errors->all())->unique() as $message)<li>{{ $message }}</li>@endforeach
        </ul></div>
    @endif

    <form method="POST" action="{{ route('waste-weighings.store') }}" id="sheet" data-days="{{ $daysInMonth }}" novalidate>
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 text-nowrap">
                    <thead>
                        <tr>
                            <th>{{ __('RW') }}</th>
                            <th>{{ __('Chairman') }}</th>
                            <th class="text-end">{{ __('Households (KK)') }}</th>
                            <th class="text-end">{{ __('Organic (kg/day)') }}</th>
                            <th class="text-end">{{ __('Inorganic (kg/day)') }}</th>
                            <th class="text-end">{{ __('B3 (kg/day)') }}</th>
                            <th class="text-end">{{ __('Residue (kg/day)') }}</th>
                            <th class="text-end">{{ __('Total (kg/day)') }}</th>
                            <th class="text-end">{{ __('Carts') }}</th>
                            <th class="text-end">{{ __('Collectors') }}</th>
                            <th class="text-end">{{ __('Pickup frequency') }}</th>
                            <th class="text-end">{{ __('Days counted') }}</th>
                            <th class="text-end">{{ __('Transported (kg/month)') }}</th>
                            <th>{{ __('Remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rws as $rw)
                            @php($r = $reports->get($rw->id))
                            <tr data-row>
                                <td class="fw-medium">{{ $rw->name }}</td>
                                <td>{{ $rw->chairman_name ?: '-' }}</td>
                                <td class="text-end">{{ $rw->household_count ?? '-' }}</td>
                                @foreach ($cols as $c)
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" style="min-width: 90px;"
                                               name="rows[{{ $rw->id }}][{{ $c }}]" data-f="{{ $c }}"
                                               value="{{ old('rows.'.$rw->id.'.'.$c, $num($r?->$c)) }}"></td>
                                @endforeach
                                <td class="text-end fw-semibold" data-total>0</td>
                                @foreach ($counts as $c)
                                    <td><input type="number" step="1" min="0" class="form-control form-control-sm text-end" style="min-width: 70px;"
                                               name="rows[{{ $rw->id }}][{{ $c }}]" data-f="{{ $c }}"
                                               value="{{ old('rows.'.$rw->id.'.'.$c, $num($r?->$c)) }}"></td>
                                @endforeach
                                <td><input type="number" step="1" min="1" max="31" class="form-control form-control-sm text-end" style="min-width: 70px;"
                                           name="rows[{{ $rw->id }}][days_counted]" data-f="days_counted"
                                           value="{{ old('rows.'.$rw->id.'.days_counted', $r?->days_counted ?? $daysInMonth) }}"></td>
                                <td class="text-end" data-transported>0</td>
                                <td><input type="text" maxlength="255" class="form-control form-control-sm" style="min-width: 160px;"
                                           name="rows[{{ $rw->id }}][notes]" value="{{ old('rows.'.$rw->id.'.notes', $r?->notes) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="2">{{ __('Total') }}</td>
                            <td class="text-end">{{ number_format((int) $rws->sum('household_count'), 0, ',', '.') }}</td>
                            @foreach ($cols as $c)<td class="text-end" data-sum="{{ $c }}">0</td>@endforeach
                            <td class="text-end" data-sum="total">0</td>
                            <td class="text-end" data-sum="carts">0</td>
                            <td class="text-end" data-sum="collectors">0</td>
                            <td></td><td></td>
                            <td class="text-end" data-sum="transported">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">{{ __('Save weighing sheet') }}</button>
            <a href="{{ route('waste-reductions.index', ['year' => $year]) }}" class="btn btn-outline-secondary">{{ __('View monthly summary') }}</a>
        </div>
        <p class="text-body-secondary small mt-2">
            {{ __('Transported = total kg/day x days counted. Pickup frequency is information only.') }}
        </p>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('sheet');
    if (!form) { return; }

    var nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    var kgKeys = ['organic_kg_per_day', 'inorganic_kg_per_day', 'b3_kg_per_day', 'residue_kg_per_day'];
    var sumKeys = kgKeys.concat(['carts', 'collectors']);

    function val(row, key) {
        var input = row.querySelector('[data-f="' + key + '"]');
        var n = parseFloat(input && input.value);
        return isNaN(n) ? 0 : n;
    }

    function round2(n) { return Math.round(n * 100) / 100; }

    function calc() {
        var sums = { total: 0, transported: 0 };
        sumKeys.forEach(function (k) { sums[k] = 0; });

        [].slice.call(form.querySelectorAll('[data-row]')).forEach(function (row) {
            var total = 0;
            kgKeys.forEach(function (k) { total += val(row, k); });

            // Blank "days" means the whole month, exactly like the server.
            var days = val(row, 'days_counted') || parseInt(form.dataset.days, 10);
            var transported = round2(total * days);

            row.querySelector('[data-total]').textContent = nf.format(round2(total));
            row.querySelector('[data-transported]').textContent = nf.format(transported);

            sumKeys.forEach(function (k) { sums[k] += val(row, k); });
            sums.total += total;
            sums.transported += transported;
        });

        Object.keys(sums).forEach(function (k) {
            var cell = form.querySelector('[data-sum="' + k + '"]');
            if (cell) { cell.textContent = nf.format(round2(sums[k])); }
        });
    }

    form.addEventListener('input', calc);
    calc();
})();
</script>
@endpush
