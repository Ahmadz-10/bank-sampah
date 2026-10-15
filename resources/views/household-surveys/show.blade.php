@extends('layouts.app')

@section('title', __('Survey details'))

@section('content')
    <x-page-header title="Survey details">
        <a href="{{ route('household-surveys.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        @if ($survey->canBeModifiedBy(auth()->user()))
            <a href="{{ route('household-surveys.edit', $survey) }}" class="btn btn-outline-primary btn-sm">{{ __('Edit') }}</a>
            <form method="POST" action="{{ route('household-surveys.destroy', $survey) }}" class="d-inline"
                  data-confirm="{{ __('Delete this survey?') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
            </form>
        @endif
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Household') }}</dt>
                        <dd class="col-sm-8">
                            <a href="{{ route('households.show', $survey->household) }}" class="text-decoration-none">
                                {{ $survey->household->household_code }} - {{ $survey->household->head_name }}
                            </a>
                        </dd>
                        <dt class="col-sm-4">{{ __('Address') }}</dt>
                        <dd class="col-sm-8">{{ $survey->household->address }}</dd>
                        <dt class="col-sm-4">{{ __('Survey date') }}</dt>
                        <dd class="col-sm-8">{{ $survey->survey_date->format('d/m/Y') }}</dd>
                        <dt class="col-sm-4">{{ __('Sorting status') }}</dt>
                        <dd class="col-sm-8">
                            <span class="badge text-bg-{{ $survey->sortingBadge() }}">{{ $survey->sortingLabel() }}</span>
                        </dd>
                        <dt class="col-sm-4">{{ __('Surveyed by') }}</dt>
                        <dd class="col-sm-8">{{ $survey->surveyor?->name ?? '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Notes') }}</dt>
                        <dd class="col-sm-8 mb-0">{!! nl2br(e($survey->notes ?: '-')) !!}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if ($survey->photo_path)
                <div class="card mb-3">
                    <img src="{{ $survey->photoUrl() }}" alt="{{ __('Survey photo') }}" class="card-img-top">
                </div>
            @endif

            <div class="card">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    {{ __('Validation') }}
                    <span class="badge text-bg-{{ $survey->validationBadge() }}">{{ $survey->validationLabel() }}</span>
                </div>
                <div class="card-body">
                    @if ($survey->isPending())
                        @can('validate-surveys')
                            <form method="POST" action="{{ route('household-surveys.approve', $survey) }}">
                                @csrf
                                <label for="validation_notes" class="form-label">{{ __('Validation notes') }}</label>
                                <textarea id="validation_notes" name="validation_notes" rows="2"
                                          class="form-control @error('validation_notes') is-invalid @enderror">{{ old('validation_notes') }}</textarea>
                                @error('validation_notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text mb-3">{{ __('Notes are required when rejecting.') }}</div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">{{ __('Validate') }}</button>
                                    <button type="submit" class="btn btn-outline-danger"
                                            formaction="{{ route('household-surveys.reject', $survey) }}">{{ __('Reject') }}</button>
                                </div>
                            </form>
                        @else
                            <p class="mb-0 text-body-secondary">{{ __('Waiting for validation by an administrator.') }}</p>
                        @endcan
                    @else
                        <dl class="row mb-0">
                            <dt class="col-sm-5">{{ $survey->isValidated() ? __('Validated by') : __('Rejected by') }}</dt>
                            <dd class="col-sm-7">{{ $survey->validator?->name ?? '-' }}</dd>
                            <dt class="col-sm-5">{{ __('Date') }}</dt>
                            <dd class="col-sm-7">{{ $survey->validated_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                            <dt class="col-sm-5">{{ __('Validation notes') }}</dt>
                            <dd class="col-sm-7 mb-0">{{ $survey->validation_notes ?: '-' }}</dd>
                        </dl>

                        @can('validate-surveys')
                            <form method="POST" action="{{ route('household-surveys.reopen', $survey) }}" class="mt-3"
                                  data-confirm="{{ __('Reopen this survey? It will go back to pending.') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Reopen') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
