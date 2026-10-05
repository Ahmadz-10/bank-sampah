# Authentication & Roles (Phase 1)

Branch: `feature/authentication` · Owner: Developer A

Delivers: login, logout, session security, 4 roles, role middleware, ability-based authorization, and the shared Bootstrap layout (guest + app shell with a role-aware sidebar).

## 1. Install

Copy the folders from the zip into the project root. This **overwrites `routes/web.php`** (fine on a fresh project).

Then make three small edits to existing files:

**a) `app/Models/User.php`** — add the trait (works with property-style or Laravel 13 attribute-style models):

```php
use App\Models\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;   // add HasRoles
```

**b) `bootstrap/app.php`** — register the two middleware aliases:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role'   => \App\Http\Middleware\EnsureUserHasRole::class,
        'active' => \App\Http\Middleware\EnsureUserIsActive::class,
    ]);
})
```

**c) `bootstrap/providers.php`** — register the authorization provider:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthorizationServiceProvider::class,
];
```

Then:

```bash
php artisan migrate:fresh --seed   # local only
npm run dev
php artisan test
```

Log in at `/login` with `superadmin@banksampah.test` / `password` (also `admin@`, `petugas@`, `pimpinan@`).

> The layouts load `resources/css/app.scss` and `resources/js/app.js` (the Bootstrap setup from Phase 0). If your Vite entry files are named differently, update the `@vite(...)` line in both layouts.

## 2. Permission matrix (proposed — confirm as a team)

| Ability | Super Admin | Admin | Petugas | Viewer |
| --- | :-: | :-: | :-: | :-: |
| `view-dashboard` | ✓ | ✓ | ✓ | ✓ |
| `manage-users` | ✓ | – | – | – |
| `manage-settings` | ✓ | – | – | – |
| `view-activity-logs` | ✓ | ✓ | – | – |
| `manage-master-data` (waste categories, prices) | ✓ | ✓ | – | – |
| `manage-households` | ✓ | ✓ | ✓ | – |
| `manage-customers` | ✓ | ✓ | ✓ | – |
| `survey-households` | ✓ | ✓ | ✓ | – |
| `validate-surveys` | ✓ | ✓ | – | – |
| `manage-transactions` (deposits, sales) | ✓ | ✓ | ✓ | – |
| `manage-monitoring` | ✓ | ✓ | – | – |
| `view-reports` | ✓ | ✓ | – | ✓ |

Source of truth: `config/permissions.php`. Changing a permission = edit that file **and** the matrix in `tests/Feature/Auth/RoleAccessTest.php`.

## 3. How other developers plug in (no edits to shared files)

**Routes** — create `routes/modules/<module>.php`. It is loaded automatically and already has `auth` + `active` middleware:

```php
// routes/modules/households.php
use App\Http\Controllers\HouseholdController;

Route::middleware('can:manage-households')->group(function () {
    Route::resource('households', HouseholdController::class);
});
```

**Sidebar** — every planned module is already listed in `config/menu.php`. The link appears automatically once a route with the expected name exists and the user has the ability. Use these route names:

`households.*`, `customers.*`, `waste-categories.*`, `waste-prices.*`, `household-surveys.*`, `waste-deposits.*`, `waste-sales.*`, `waste-reductions.*`, `reports.*`, `users.*`, `settings.*`, `activity-logs.*`

**In Blade / controllers**

```blade
@can('validate-surveys') ... @endcan
```
```php
$this->authorize('manage-households');   // or Gate::allows(...)
```

**Page layout** — `@extends('layouts.app')` with `@section('title', ...)` and `@section('content')`.

## 4. Security behaviours included

- Session ID regenerated on login; session invalidated + CSRF token rotated on logout.
- Login throttled: 5 failed attempts per email+IP, then locked out temporarily.
- Wrong password and inactive account give the same generic message.
- Accounts deactivated mid-session are logged out on their next request.
- A user with no role can log in but gets a 403 (with a logout button) until a role is assigned.

## 5. Known limits / next steps

- `users` CRUD (assigning roles, activating/deactivating) arrives with **Phase 2 (Master Data)**; until then use the seeder or `php artisan tinker`.
- No password reset / self-registration — accounts are created by a Super Admin (matches the document). Add later if wanted.
- Login/logout are not yet written to `activity_logs` (that is Phase 8).
- UI text uses English keys translated by `lang/id.json`; `APP_LOCALE=id` shows Indonesian.
