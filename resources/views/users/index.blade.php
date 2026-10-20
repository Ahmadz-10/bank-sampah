@extends('layouts.app')

@section('title', __('Users'))

@section('content')
    <x-page-header title="Users">
        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">{{ __('Add user') }}</a>
    </x-page-header>

    <form method="GET" action="{{ route('users.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $term }}" class="form-control" placeholder="{{ __('Search name or email') }}">
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select">
                <option value="">{{ __('All roles') }}</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) request('role') === (string) $role->id)>{{ $role->label }}</option>
                @endforeach
            </select>
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
            @if ($term !== '' || request()->filled('role') || request()->filled('status'))
                <a href="{{ route('users.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Last login') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                {{ $user->name }}
                                @if ($user->is(auth()->user()))
                                    <span class="badge text-bg-light border">{{ __('You') }}</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role?->label ?? '-' }}</td>
                            <td class="text-nowrap">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $user->is_active ? 'success' : 'secondary' }}">
                                    {{ $user->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $user) }}">{{ __('Edit') }}</a>
                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="d-inline"
                                          data-confirm="{{ $user->is_active ? __('Deactivate this user?') : __('Activate this user?') }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $user->is_active ? 'danger' : 'success' }}">
                                            {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No data found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
