<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Roles are fixed (super_admin, admin, officer, viewer): permissions in
 * config/permissions.php are keyed by role name, so roles cannot be created or deleted
 * here. Only the display label and description can be edited.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')->orderBy('id')->get();
        $abilities = config('permissions.abilities', []);

        return view('roles.index', compact('roles', 'abilities'));
    }

    public function edit(Role $role): View
    {
        return view('roles.form', compact('role'));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        return redirect()->route('roles.index')
            ->with('status', __('Role :name was updated.', ['name' => $role->label]));
    }
}
