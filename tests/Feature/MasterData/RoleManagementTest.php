<?php

namespace Tests\Feature\MasterData;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_super_admin_can_see_roles(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get(route('roles.index'))->assertOk();
        $this->actingAs($this->userWithRole('admin'))->get(route('roles.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('officer'))->get(route('roles.index'))->assertForbidden();
    }

    public function test_the_index_lists_roles_with_user_counts_and_the_permission_matrix(): void
    {
        $this->userWithRole('officer');
        $this->userWithRole('officer');

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('roles.index'))
            ->assertOk()
            ->assertViewHas('roles', fn ($roles) => (int) $roles->firstWhere('name', 'officer')->users_count === 2)
            ->assertSee('manage-households');
    }

    public function test_label_and_description_can_be_edited_but_the_identifier_cannot(): void
    {
        $super = $this->userWithRole('super_admin');
        $role = Role::firstOrCreate(['name' => 'officer'], ['label' => 'Officer']);

        $this->actingAs($super)
            ->put(route('roles.update', $role), [
                'label' => 'Petugas Lapangan',
                'description' => 'Menginput survei dan transaksi.',
                'name' => 'hacked',       // must be ignored
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('Petugas Lapangan', $role->label);
        $this->assertSame('officer', $role->name);
    }

    public function test_label_is_required(): void
    {
        $role = Role::firstOrCreate(['name' => 'officer'], ['label' => 'Officer']);

        $this->actingAs($this->userWithRole('super_admin'))
            ->put(route('roles.update', $role), ['label' => ''])
            ->assertSessionHasErrors('label');
    }

    public function test_roles_cannot_be_created_or_deleted(): void
    {
        $super = $this->userWithRole('super_admin');
        $role = Role::firstOrCreate(['name' => 'officer'], ['label' => 'Officer']);

        $this->actingAs($super)->post('/roles', ['label' => 'X'])->assertStatus(405);
        $this->actingAs($super)->delete(route('roles.update', $role))->assertStatus(405);
    }
}
