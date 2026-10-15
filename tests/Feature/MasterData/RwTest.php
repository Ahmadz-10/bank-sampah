<?php

namespace Tests\Feature\MasterData;

use App\Models\Household;
use App\Models\Rw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class RwTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_admins_manage_rws(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('rws.index'))->assertOk()->assertViewHas('rws', fn ($r) => $r->count() === 11);
        $this->actingAs($this->userWithRole('super_admin'))->get(route('rws.index'))->assertOk();
        $this->actingAs($this->userWithRole('officer'))->get(route('rws.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('viewer'))->get(route('rws.index'))->assertForbidden();
    }

    public function test_an_rw_can_be_added_with_the_next_free_number_suggested(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('rws.create'))->assertOk()->assertViewHas('rw', fn ($rw) => $rw->number === 12);

        $this->actingAs($admin)->post(route('rws.store'), ['number' => 12, 'chairman_name' => 'Bapak Ahmad', 'household_count' => 340, 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('rws.index'));

        $rw = Rw::where('number', 12)->firstOrFail();
        $this->assertSame('RW 12', $rw->name);
        $this->assertSame('Bapak Ahmad', $rw->chairman_name);
        $this->assertSame(340, $rw->household_count);
    }

    public function test_the_chairman_and_official_household_count_can_be_filled_in(): void
    {
        $rw = Rw::where('number', 1)->firstOrFail();

        $this->actingAs($this->userWithRole('admin'))->put(route('rws.update', $rw), [
            'number' => 1, 'chairman_name' => 'Ibu Sari', 'household_count' => 512, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $rw->refresh();
        $this->assertSame('Ibu Sari', $rw->chairman_name);
        $this->assertSame(512, $rw->household_count);
    }

    public function test_validation(): void
    {
        $admin = $this->userWithRole('admin');
        $rw = Rw::where('number', 2)->firstOrFail();

        $this->actingAs($admin)->post(route('rws.store'), ['number' => 1])->assertSessionHasErrors('number');          // taken
        $this->actingAs($admin)->post(route('rws.store'), ['number' => 0])->assertSessionHasErrors('number');
        $this->actingAs($admin)->post(route('rws.store'), ['number' => 100])->assertSessionHasErrors('number');
        $this->actingAs($admin)->post(route('rws.store'), ['number' => 20, 'household_count' => -5])->assertSessionHasErrors('household_count');
        $this->actingAs($admin)->put(route('rws.update', $rw), ['number' => 3])->assertSessionHasErrors('number');      // RW 03 exists
        $this->actingAs($admin)->put(route('rws.update', $rw), ['number' => 2])->assertSessionHasNoErrors();           // keeping its own is fine

        $this->assertSame(11, Rw::count());
    }

    public function test_an_rw_cannot_be_deleted(): void
    {
        $rw = Rw::where('number', 1)->firstOrFail();

        $this->actingAs($this->userWithRole('admin'))->delete('/rws/'.$rw->id)->assertStatus(405);

        $this->assertModelExists($rw);
    }

    public function test_the_list_counts_registered_households(): void
    {
        $rw = Rw::where('number', 4)->firstOrFail();
        Household::factory()->count(3)->create(['rw_id' => $rw->id]);

        $this->actingAs($this->userWithRole('admin'))->get(route('rws.index'))
            ->assertViewHas('rws', fn ($rws) => $rws->firstWhere('id', $rw->id)->households_count === 3);
    }
}
