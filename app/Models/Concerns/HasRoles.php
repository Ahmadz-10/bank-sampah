<?php

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Add to App\Models\User:  use HasRoles;
 *
 * A trait (instead of editing User.php directly) keeps this independent of
 * the User model style (property-based or Laravel 13 attributes) and avoids
 * merge conflicts on a shared model.
 */
trait HasRoles
{
    /** Called automatically by Eloquent when the model is constructed. */
    public function initializeHasRoles(): void
    {
        $this->mergeCasts([
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ]);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->role !== null && in_array($this->role->name, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }
}
