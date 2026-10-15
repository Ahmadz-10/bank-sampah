@extends('layouts.app')

@section('title', $household->household_code)

@section('content')
    <x-page-header :title="$household->head_name">
        <a href="{{ route('households.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        <a href="{{ route('households.edit', $household) }}" class="btn btn-outline-primary btn-sm">{{ __('Edit') }}</a>
        @can('survey-households')
            <a href="{{ route('household-surveys.create', ['household' => $household->id]) }}" class="btn btn-primary btn-sm">{{ __('New survey') }}</a>
        @endcan
    </x-page-header>

    @php($latestValidated = $surveys->firstWhere('validation_status', \App\Models\HouseholdSurvey::VALIDATED))

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Household details') }}</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Household code') }}</dt>
                        <dd class="col-sm-8">{{ $household->household_code }}</dd>
                        <dt class="col-sm-4">{{ __('Address') }}</dt>
                        <dd class="col-sm-8">{{ $household->address }}</dd>
                        <dt class="col-sm-4">{{ __('RT / RW') }}</dt>
                        <dd class="col-sm-8">{{ $household->rt ?: '-' }} / {{ $household->rw?->name ?? '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Phone') }}</dt>
                        <dd class="col-sm-8">{{ $household->phone ?: '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Family members') }}</dt>
                        <dd class="col-sm-8">{{ $household->member_count }}</dd>
                        <dt class="col-sm-4">{{ __('Registered by') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $household->creator?->name ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Sorting status') }}</div>
                <div class="card-body">
                    <span class="badge fs-6 text-bg-{{ $household->statusBadge() }}">{{ $household->statusLabel() }}</span>
                    <p class="text-body-secondary small mt-2 mb-0">
                        @if ($latestValidated)
                            {{ __('Based on the validated survey of :date.', ['date' => $latestValidated->survey_date->format('d/m/Y')]) }}
                        @else
                            {{ __('No validated survey yet.') }}
                        @endif
                    </p>
                    @if ($surveys->where('validation_status', 'pending')->count() > 0)
                        <p class="small mt-2 mb-0">
                            <span class="badge text-bg-secondary">{{ $surveys->where('validation_status', 'pending')->count() }}</span>
                            {{ __('survey(s) waiting for validation') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">{{ __('Customers') }}</div>
        @if ($household->customers->isEmpty())
            <div class="card-body text-body-secondary">{{ __('No customers registered for this household.') }}</div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($household->customers as $customer)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $customer->customer_code }} &middot; {{ $customer->name }}</span>
                        <span class="badge text-bg-{{ $customer->is_active ? 'success' : 'secondary' }}">
                            {{ $customer->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="card">
        <div class="card-header fw-semibold">{{ __('Survey history') }}</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Survey date') }}</th>
                        <th>{{ __('Sorting status') }}</th>
                        <th>{{ __('Validation') }}</th>
                        <th>{{ __('Surveyed by') }}</th>
                        <th>{{ __('Validated by') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($surveys as $survey)
                        <tr>
                            <td class="text-nowrap">{{ $survey->survey_date->format('d/m/Y') }}</td>
                            <td><span class="badge text-bg-{{ $survey->sortingBadge() }}">{{ $survey->sortingLabel() }}</span></td>
                            <td><span class="badge text-bg-{{ $survey->validationBadge() }}">{{ $survey->validationLabel() }}</span></td>
                            <td>{{ $survey->surveyor?->name ?? '-' }}</td>
                            <td>{{ $survey->validator?->name ?? '-' }}</td>
                            <td class="text-end">
                                @can('survey-households')
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('household-surveys.show', $survey) }}">{{ __('View') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No surveys yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
