@extends('layouts.app')

@section('title', __('Set target'))

@section('content')
    <x-page-header title="Set target" />

    <form method="POST" action="{{ route('waste-reductions.update', $reduction) }}" novalidate class="card card-body" style="max-width: 560px;">
        @csrf
        @method('PUT')

        <dl class="row">
            <dt class="col-sm-5">{{ __('Month') }}</dt>
            <dd class="col-sm-7">{{ __(\Illuminate\Support\Carbon::create(2000, $reduction->month, 1)->format('F')) }} {{ $reduction->year }}</dd>
            <dt class="col-sm-5">{{ __('Reduction') }}</dt>
            <dd class="col-sm-7">{{ \App\Support\Format::weight($reduction->reduction_percentage) }}% <span class="text-body-secondary small">({{ __('calculated') }})</span></dd>
        </dl>

        <x-form.input name="target_percentage" label="Target (%)" type="number" min="0" max="100" step="0.01" :value="$reduction->target_percentage" required />
        <x-form.textarea name="notes" label="Notes" :value="$reduction->notes" rows="3" />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('waste-reductions.index', ['year' => $reduction->year]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
