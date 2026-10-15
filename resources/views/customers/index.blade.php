@extends('layouts.app')

@section('title', __('Customers'))

@section('content')
    <x-page-header title="Customers">
        <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">{{ __('Add customer') }}</a>
    </x-page-header>

    <form method="GET" action="{{ route('customers.index') }}" class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="search" name="q" value="{{ $term }}" class="form-control"
                   placeholder="{{ __('Search code, name, phone, or ID number') }}">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            @if ($term !== '' || request()->filled('status'))
                <a href="{{ route('customers.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Household') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Joined') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none">{{ $customer->customer_code }}</a>
                            </td>
                            <td>{{ $customer->name }}</td>
                            <td class="text-nowrap">
                                @if ($customer->household)
                                    {{ $customer->household->household_code }}
                                    <span class="text-body-secondary">{{ $customer->household->head_name }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $customer->phone ?: '-' }}</td>
                            <td class="text-nowrap">{{ $customer->joined_at?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $customer->is_active ? 'success' : 'secondary' }}">
                                    {{ $customer->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('customers.show', $customer) }}">{{ __('View') }}</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('customers.edit', $customer) }}">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="d-inline"
                                      data-confirm="{{ __('Delete this customer?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $customers->links('pagination::bootstrap-5') }}</div>
@endsection
