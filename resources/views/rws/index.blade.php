@extends('layouts.app')

@section('title', __('RWs'))

@section('content')
    <x-page-header title="RWs">
        <a href="{{ route('rws.create') }}" class="btn btn-primary btn-sm">{{ __('Add RW') }}</a>
    </x-page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('RW') }}</th>
                        <th>{{ __('Chairman') }}</th>
                        <th class="text-end">{{ __('Households (KK)') }}</th>
                        <th class="text-end">{{ __('Registered in the system') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rws as $rw)
                        <tr>
                            <td class="fw-medium">{{ $rw->name }}</td>
                            <td>{{ $rw->chairman_name ?: '-' }}</td>
                            <td class="text-end">{{ $rw->household_count ?? '-' }}</td>
                            <td class="text-end">{{ $rw->households_count }}</td>
                            <td><span class="badge text-bg-{{ $rw->is_active ? 'success' : 'secondary' }}">{{ $rw->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('rws.edit', $rw) }}">{{ __('Edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
