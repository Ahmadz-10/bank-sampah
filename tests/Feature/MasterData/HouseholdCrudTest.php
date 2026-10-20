<?php

namespace Tests\Feature\MasterData;

use App\Models\Household;
use App\Models\Rw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class HouseholdCrudTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'head_name' => 'Budi Santoso',
            'address' => 'Jl. Melati No. 5',
            'rt' => '001',
            'rw_id' => Rw::where('number', 2)->value('id'),
            'phone' => '081234567890',
            'member_count' => 4,
        ], $overrides);
    }

    public function test_officer_and_admin_can_view_the_list(): void
    {
        Household::factory()->count(3)->create();

        foreach (['officer', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('households.index'))
                ->assertOk()
                ->assertViewHas('households');
        }
    }

    public function test_viewer_is_forbidden(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get(route('households.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('households.store'), $this->validData())->assertForbidden();
        $this->assertDatabaseCount('households', 0);
    }

    public function test_household_is_created_with_a_generated_code(): void
    {
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)
            ->post(route('households.store'), $this->validData())
            ->assertRedirect(route('households.index'));

        $household = Household::firstOrFail();
        $this->assertSame('Budi Santoso', $household->head_name);
        $this->assertMatchesRegularExpression('/^RMH-\d{4}$/', $household->household_code);
        $this->assertSame($officer->id, $household->created_by);
        $this->assertSame('unknown', $household->fresh()->sorting_status);
    }

    public function test_a_household_is_linked_to_an_rw_and_the_list_shows_its_name(): void
    {
        $this->actingAs($this->userWithRole('officer'))
            ->post(route('households.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $household = Household::firstOrFail();
        $this->assertSame('RW 02', $household->rw->name);

        $this->actingAs($this->userWithRole('officer'))->get(route('households.index'))->assertSee('RW 02');
    }

    public function test_the_rw_is_optional_but_must_exist_when_given(): void
    {
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)->post(route('households.store'), $this->validData(['rw_id' => null]))->assertSessionHasNoErrors();
        $this->assertNull(Household::firstOrFail()->rw_id);

        $this->actingAs($officer)->post(route('households.store'), $this->validData(['rw_id' => 9999]))->assertSessionHasErrors('rw_id');
    }

    public function test_the_form_offers_only_active_rws(): void
    {
        Rw::where('number', 11)->update(['is_active' => false]);

        $this->actingAs($this->userWithRole('officer'))->get(route('households.create'))
            ->assertOk()
            ->assertViewHas('rwOptions', fn ($options) => count($options) === 10 && ! in_array('RW 11', $options, true));
    }

    public function test_codes_are_never_reused_even_after_a_soft_delete(): void
    {
        $first = Household::factory()->create();
        $first->delete();

        $second = Household::factory()->create();

        $this->assertNotSame($first->household_code, $second->household_code);
    }

    public function test_validation_rejects_a_missing_name(): void
    {
        $this->actingAs($this->userWithRole('officer'))
            ->post(route('households.store'), $this->validData(['head_name' => '']))
            ->assertSessionHasErrors('head_name');

        $this->assertDatabaseCount('households', 0);
    }

    public function test_member_count_must_be_at_least_one(): void
    {
        $this->actingAs($this->userWithRole('officer'))
            ->post(route('households.store'), $this->validData(['member_count' => 0]))
            ->assertSessionHasErrors('member_count');
    }

    public function test_update_changes_data_but_never_the_code_or_status(): void
    {
        $household = Household::factory()->create();
        $code = $household->household_code;

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('households.update', $household), $this->validData([
                'head_name' => 'Siti Aminah',
                'household_code' => 'HACKED',
                'sorting_status' => 'sorting',
            ]))
            ->assertRedirect(route('households.index'));

        $household->refresh();
        $this->assertSame('Siti Aminah', $household->head_name);
        $this->assertSame($code, $household->household_code);
        $this->assertSame('unknown', $household->sorting_status);
    }

    public function test_delete_is_a_soft_delete(): void
    {
        $household = Household::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('households.destroy', $household))
            ->assertRedirect(route('households.index'));

        $this->assertSoftDeleted($household);
    }

    public function test_search_filters_the_list(): void
    {
        Household::factory()->create(['head_name' => 'Budi Santoso']);
        Household::factory()->create(['head_name' => 'Siti Aminah']);

        $this->actingAs($this->userWithRole('officer'))
            ->get(route('households.index', ['q' => 'Budi']))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }
}
