<?php

namespace Tests\Feature\MasterData;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function roleId(string $name): int
    {
        return Role::firstOrCreate(['name' => $name], ['label' => ucfirst($name)])->id;
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Petugas Baru',
            'email' => 'baru@banksampah.test',
            'phone' => '081234567890',
            'role_id' => $this->roleId('officer'),
            'is_active' => 1,
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ], $overrides);
    }

    public function test_only_super_admin_can_manage_users(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get(route('users.index'))->assertOk();
        $this->actingAs($this->userWithRole('admin'))->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('officer'))->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('viewer'))->get(route('users.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_a_user_with_a_hashed_password_and_a_role(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->post(route('users.store'), $this->validData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'baru@banksampah.test')->firstOrFail();
        $this->assertSame('officer', $user->role->name);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('secret-pass-1', $user->password);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
    }

    public function test_a_new_users_credentials_validate(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->post(route('users.store'), $this->validData());

        $this->assertTrue(Auth::validate(['email' => 'baru@banksampah.test', 'password' => 'secret-pass-1']));
        $this->assertFalse(Auth::validate(['email' => 'baru@banksampah.test', 'password' => 'wrong-password']));
    }

    public function test_validation_rules(): void
    {
        $admin = $this->userWithRole('super_admin');
        $existing = $this->userWithRole('viewer');

        $this->actingAs($admin)->post(route('users.store'), $this->validData(['email' => $existing->email]))
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('users.store'), $this->validData(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)->post(route('users.store'), $this->validData(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)->post(route('users.store'), $this->validData(['role_id' => 9999]))
            ->assertSessionHasErrors('role_id');

        $this->actingAs($admin)->post(route('users.store'), $this->validData(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('password');
    }

    public function test_updating_without_a_password_keeps_the_old_one(): void
    {
        $admin = $this->userWithRole('super_admin');
        $user = $this->userWithRole('officer');
        $oldHash = $user->password;

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Renamed', 'email' => $user->email, 'role_id' => $this->roleId('viewer'), 'is_active' => 1,
                'password' => '', 'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame('viewer', $user->role->name);
        $this->assertSame($oldHash, $user->password);
    }

    public function test_updating_with_a_password_changes_it(): void
    {
        $admin = $this->userWithRole('super_admin');
        $user = $this->userWithRole('officer');

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => 1,
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_a_super_admin_cannot_change_their_own_role_or_status(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => 'Still Me', 'email' => $admin->email,
                'role_id' => $this->roleId('viewer'), 'is_active' => 0,
            ])
            ->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Still Me', $admin->name);       // other fields do change
        $this->assertSame('super_admin', $admin->role->name);
        $this->assertTrue($admin->is_active);
    }

    public function test_editing_yourself_does_not_require_role_fields(): void
    {
        $admin = $this->userWithRole('super_admin');

        // Disabled inputs are not submitted by the browser
        $this->actingAs($admin)
            ->put(route('users.update', $admin), ['name' => 'Me', 'email' => $admin->email])
            ->assertSessionHasNoErrors();
    }

    public function test_toggle_active_deactivates_and_reactivates_another_user(): void
    {
        $admin = $this->userWithRole('super_admin');
        $user = $this->userWithRole('officer');

        $this->actingAs($admin)->patch(route('users.toggle-active', $user))->assertSessionHas('status');
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->patch(route('users.toggle-active', $user));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_you_cannot_deactivate_yourself(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->patch(route('users.toggle-active', $admin))->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $user = $this->userWithRole('officer', ['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->userWithRole('officer', ['name' => 'Zainal Petugas']);
        $this->userWithRole('viewer', ['name' => 'Yusuf Pimpinan']);

        $this->actingAs($admin)->get(route('users.index', ['q' => 'Zainal']))
            ->assertOk()->assertSee('Zainal Petugas')->assertDontSee('Yusuf Pimpinan');

        $this->actingAs($admin)->get(route('users.index', ['role' => $this->roleId('viewer')]))
            ->assertOk()->assertSee('Yusuf Pimpinan')->assertDontSee('Zainal Petugas');
    }
}
