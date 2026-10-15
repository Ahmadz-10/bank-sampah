@extends('layouts.app')

@section('title', $rw->exists ? __('Edit RW') : __('Add RW'))

@section('content')
    <x-page-header :title="$rw->exists ? 'Edit RW' : 'Add RW'" />

    <form method="POST" novalidate class="card card-body" style="max-width: 560px;"
          action="{{ $rw->exists ? route('rws.update', $rw) : route('rws.store') }}">
        @csrf
        @if ($rw->exists) @method('PUT') @endif

        <x-form.input name="number" label="RW number" type="number" min="1" max="99" :value="$rw->number" required />
        <x-form.input name="chairman_name" label="Chairman" :value="$rw->chairman_name" />
        <x-form.input name="household_count" label="Households (KK)" type="number" min="0" :value="$rw->household_count"
                      help="The official number of households (Jumlah KK) of this RW." />
        <x-form.checkbox name="is_active" label="Active" :checked="$rw->is_active" />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('rws.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
