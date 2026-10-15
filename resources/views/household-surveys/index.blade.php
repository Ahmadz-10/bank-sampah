@extends('layouts.app')

@section('title', __('Household Survey'))

@section('content')
    <x-page-header title="Household Survey">
        <a href="{{ route('household-surveys.create') }}" class="btn btn-primary btn-sm">{{ __('New survey') }}</a>
    </x-page-header>

    {{-- Quick filter by validation status, with totals --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['' => 'All', 'pending' => 'Pending', 'validated' => 'Validated', 'rejected' => 'Rejected'] as $value => $label)
            <a href="{{ route('household-surveys.index', array_filter(array_merge(request()->except('page'), ['status' => $value]))) }}"
               class="btn btn-sm {{ (string) request('status', '') === (string) $value ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __($label) }}
                <span class="badge text-bg-light">{{ $value === '' ? (int) $counts->sum() : (int) ($counts[$value] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('household-surveys.index') }}" class="row g-2 mb-3">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $term }}" class="form-control" placeholder="{{ __('Search household code or name') }}">
        </div>
        <div class="col-md-2">
            <select name="sorting" class="form-select">
                <option value="">{{ __('All statuses') }}</option>
                <option value="sorting" @selected(request('sorting') === 'sorting')>{{ __('Sorting') }}</option>
                <option value="not_sorting" @selected(request('sorting') === 'not_sorting')>{{ __('Not sorting') }}</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" title="{{ __('From date') }}">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" title="{{ __('To date') }}">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            @if ($term !== '' || request()->filled('sorting') || request()->filled('from') || request()->filled('to') || request()->filled('status'))
                <a href="{{ route('household-surveys.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Survey date') }}</th>
                        <th>{{ __('Household') }}</th>
                        <th>{{ __('Sorting status') }}</th>
                        <th>{{ __('Validation') }}</th>
                        <th>{{ __('Surveyed by') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($surveys as $survey)
                        <tr>
                            <td class="text-nowrap">{{ $survey->survey_date->format('d/m/Y') }}</td>
                            <td>
                                {{ $survey->household->household_code }}
                                <span class="text-body-secondary">{{ $survey->household->head_name }}</span>
                            </td>
                            <td><span class="badge text-bg-{{ $survey->sortingBadge() }}">{{ $survey->sortingLabel() }}</span></td>
                            <td><span class="badge text-bg-{{ $survey->validationBadge() }}">{{ $survey->validationLabel() }}</span></td>
                            <td>{{ $survey->surveyor?->name ?? '-' }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('household-surveys.show', $survey) }}">{{ __('View') }}</a>
                                @if ($survey->canBeModifiedBy(auth()->user()))
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('household-surveys.edit', $survey) }}">{{ __('Edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $surveys->links('pagination::bootstrap-5') }}</div>
@endsection
