@extends('layouts.app')

@section('title', __('Roles'))

@section('content')
    <x-page-header title="Roles" />

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Identifier') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th class="text-center">{{ __('Users') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="fw-medium">{{ $role->label }}</td>
                            <td><code>{{ $role->name }}</code></td>
                            <td>{{ $role->description ?: '-' }}</td>
                            <td class="text-center">{{ $role->users_count }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('roles.edit', $role) }}">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="h5">{{ __('Permissions') }}</h2>
    <p class="text-body-secondary small">
        {{ __('Read-only. Permissions are defined in config/permissions.php.') }}
    </p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Permission') }}</th>
                        @foreach ($roles as $role)
                            <th class="text-center">{{ $role->label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($abilities as $ability => $allowedRoles)
                        <tr>
                            <td><code>{{ $ability }}</code></td>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    @if ($role->name === 'super_admin' || in_array($role->name, $allowedRoles, true))
                                        <span class="text-success">&#10003;</span>
                                    @else
                                        <span class="text-body-secondary">&ndash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
