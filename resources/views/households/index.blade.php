@extends('layouts.app')

@section('title', __('Households'))

@section('content')
    <x-page-header title="Households">
        <a href="{{ route('households.create') }}" class="btn btn-primary btn-sm">{{ __('Add household') }}</a>
    </x-page-header>

    <form method="GET" action="{{ route('households.index') }}" class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ $term }}" class="form-control"
                   placeholder="{{ __('Search code, name, or address') }}">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (['sorting' => 'Sorting', 'not_sorting' => 'Not sorting', 'unknown' => 'Not surveyed'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            @if ($term !== '' || request()->filled('status'))
                <a href="{{ route('households.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Head of household') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('RT / RW') }}</th>
                        <th class="text-center">{{ __('Members') }}</th>
                        <th>{{ __('Sorting status') }}</th>
                        <th class="text-center">{{ __('Customers') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($households as $household)
                        <tr>
                            <td class="text-nowrap">{{ $household->household_code }}</td>
                            <td>{{ $household->head_name }}</td>
                            <td>{{ $household->address }}</td>
                            <td class="text-nowrap">{{ $household->rt ?: '-' }} / {{ $household->rw ?: '-' }}</td>
                            <td class="text-center">{{ $household->member_count }}</td>
                            <td><span class="badge text-bg-{{ $household->statusBadge() }}">{{ $household->statusLabel() }}</span></td>
                            <td class="text-center">{{ $household->customers_count }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('households.edit', $household) }}">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('households.destroy', $household) }}" class="d-inline"
                                      data-confirm="{{ __('Delete this household?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $households->links('pagination::bootstrap-5') }}</div>
@endsection
