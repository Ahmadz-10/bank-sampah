@extends('layouts.app')

@section('title', $survey->exists ? __('Edit survey') : __('New survey'))

@section('content')
    <x-page-header :title="$survey->exists ? 'Edit survey' : 'New survey'" />

    <form method="POST" novalidate enctype="multipart/form-data" class="card card-body" style="max-width: 720px;"
          action="{{ $survey->exists ? route('household-surveys.update', $survey) : route('household-surveys.store') }}">
        @csrf
        @if ($survey->exists)
            @method('PUT')

            @if ($survey->isRejected())
                <div class="alert alert-warning">
                    <strong>{{ __('This survey was rejected.') }}</strong>
                    {{ $survey->validation_notes }}
                    <div class="small mt-1">{{ __('Saving your changes will resubmit it for validation.') }}</div>
                </div>
            @endif

            <div class="mb-3">
                <label class="form-label">{{ __('Household') }}</label>
                <input type="text" class="form-control" disabled
                       value="{{ $survey->household->household_code }} - {{ $survey->household->head_name }}">
            </div>
        @else
            <x-form.select name="household_id" label="Household" :options="$householdOptions"
                           :value="$survey->household_id" placeholder="Select a household" required />
        @endif

        <x-form.input name="survey_date" label="Survey date" type="date"
                      :value="$survey->survey_date?->format('Y-m-d')" :max="today()->format('Y-m-d')" required />

        <div class="mb-3">
            <span class="form-label d-block">{{ __('Sorting status') }} <span class="text-danger">*</span></span>
            @foreach ([\App\Models\Household::STATUS_SORTING => 'Sorting', \App\Models\Household::STATUS_NOT_SORTING => 'Not sorting'] as $value => $label)
                <div class="form-check form-check-inline">
                    <input class="form-check-input @error('sorting_status') is-invalid @enderror" type="radio"
                           name="sorting_status" id="sorting_{{ $value }}" value="{{ $value }}"
                           @checked(old('sorting_status', $survey->sorting_status) === $value)>
                    <label class="form-check-label" for="sorting_{{ $value }}">{{ __($label) }}</label>
                </div>
            @endforeach
            @error('sorting_status')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <x-form.textarea name="notes" label="Notes" :value="$survey->notes" rows="3" />

        <div class="mb-3">
            <label for="photo" class="form-label">{{ __('Photo') }}</label>
            <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                   class="form-control @error('photo') is-invalid @enderror">
            <div class="form-text">{{ __('Optional. JPG, PNG or WebP, up to 4 MB.') }}</div>
            @error('photo')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            @if ($survey->photo_path)
                <div class="mt-2">
                    <img src="{{ $survey->photoUrl() }}" alt="" class="img-thumbnail" style="max-height: 160px;">
                    <x-form.checkbox name="remove_photo" label="Remove current photo" />
                </div>
            @endif
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $survey->exists ? route('household-surveys.show', $survey) : route('household-surveys.index') }}"
               class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
