<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Users are deactivated, never deleted, so activity history stays attributable.
 * A Super Admin cannot change their own role or status (prevents locking yourself out).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q'));

        $users = User::query()
            ->with('role')
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($request->filled('role'), fn ($query) => $query->where('role_id', $request->input('role')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('id')->get();

        return view('users.index', compact('users', 'roles', 'term'));
    }

    public function create(): View
    {
        $user = new User;
        $user->is_active = true;

        return view('users.form', ['user' => $user, 'roleOptions' => $this->roleOptions()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // role_id / is_active are not mass-assignable on User, so they are set explicitly.
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],   // hashed by the model's 'hashed' cast
        ]);
        $user->forceFill([
            'role_id' => $data['role_id'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'],
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('users.index')
            ->with('status', __('User :name was created.', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user, 'roleOptions' => $this->roleOptions()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        $user->phone = $data['phone'] ?? null;

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if (! $request->editingSelf()) {
            $user->role_id = $data['role_id'];
            $user->is_active = $data['is_active'];
        }

        $user->save();

        return redirect()->route('users.index')
            ->with('status', __('User :name was updated.', ['name' => $user->name]));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('You cannot deactivate your own account.'));
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('status', $user->is_active
            ? __('User :name was activated.', ['name' => $user->name])
            : __('User :name was deactivated.', ['name' => $user->name]));
    }

    /** @return array<int, string> role id => label */
    private function roleOptions(): array
    {
        return Role::orderBy('id')->pluck('label', 'id')->all();
    }
}
