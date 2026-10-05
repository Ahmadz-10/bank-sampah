<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;

trait CreatesUsersWithRoles
{
    /**
     * Create a user with the given role name (or no role when null).
     * Pass ['is_active' => false] to create a deactivated account.
     */
    protected function userWithRole(?string $role, array $attributes = []): User
    {
        $user = User::factory()->create(Arr::except($attributes, ['is_active']));

        $user->forceFill([
            'role_id' => $role
                ? Role::firstOrCreate(['name' => $role], ['label' => ucfirst(str_replace('_', ' ', $role))])->id
                : null,
            'is_active' => $attributes['is_active'] ?? true,
        ])->save();

        return $user->refresh();
    }
}
