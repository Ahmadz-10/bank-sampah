<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Route::middleware(['web', 'auth', 'active', 'role:admin'])
            ->get('/_test/admin-only', fn () => 'ok');
    }

    public function test_role_middleware_allows_matching_role_and_super_admin(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/_test/admin-only')->assertOk();
        $this->actingAs($this->userWithRole('super_admin'))->get('/_test/admin-only')->assertOk();
    }

    public function test_role_middleware_blocks_other_roles(): void
    {
        $this->actingAs($this->userWithRole('officer'))->get('/_test/admin-only')->assertForbidden();
        $this->actingAs($this->userWithRole('viewer'))->get('/_test/admin-only')->assertForbidden();
    }

    public function test_guest_is_sent_to_login_not_403(): void
    {
        $this->get('/_test/admin-only')->assertRedirect(route('login'));
    }

    public function test_every_role_can_open_the_dashboard(): void
    {
        foreach (['super_admin', 'admin', 'officer', 'viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/dashboard')->assertOk();
        }
    }

    public function test_user_without_a_role_gets_403_on_dashboard(): void
    {
        $this->actingAs($this->userWithRole(null))->get('/dashboard')->assertForbidden();
    }

    /**
     * The agreed permission matrix, written out independently of config/permissions.php
     * so that changing a permission is always a deliberate, reviewed edit.
     */
    public function test_permission_matrix(): void
    {
        $matrix = [
            'view-dashboard'      => ['admin', 'officer', 'viewer'],
            'manage-users'        => [],
            'manage-settings'     => [],
            'view-activity-logs'  => ['admin'],
            'manage-master-data'  => ['admin'],
            'manage-households'   => ['admin', 'officer'],
            'manage-customers'    => ['admin', 'officer'],
            'survey-households'   => ['admin', 'officer'],
            'validate-surveys'    => ['admin'],
            'manage-transactions' => ['admin', 'officer'],
            'manage-monitoring'   => ['admin'],
            'view-reports'        => ['admin', 'viewer'],
        ];

        foreach (['super_admin', 'admin', 'officer', 'viewer'] as $role) {
            $user = $this->userWithRole($role);

            foreach ($matrix as $ability => $allowed) {
                $expected = $role === 'super_admin' || in_array($role, $allowed, true);

                $this->assertSame(
                    $expected,
                    Gate::forUser($user)->allows($ability),
                    "{$role} / {$ability}"
                );
            }
        }
    }
}
