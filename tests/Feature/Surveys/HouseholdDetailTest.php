<?php

namespace Tests\Feature\Surveys;

use App\Models\Customer;
use App\Models\Household;
use App\Models\HouseholdSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class HouseholdDetailTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_officer_and_admin_can_open_the_detail_page_but_viewer_cannot(): void
    {
        $household = Household::factory()->create();

        $this->actingAs($this->userWithRole('officer'))->get(route('households.show', $household))->assertOk();
        $this->actingAs($this->userWithRole('admin'))->get(route('households.show', $household))->assertOk();
        $this->actingAs($this->userWithRole('viewer'))->get(route('households.show', $household))->assertForbidden();
    }

    public function test_it_lists_the_survey_history_newest_first_and_the_customers(): void
    {
        $household = Household::factory()->create();
        $old = HouseholdSurvey::factory()->create(['household_id' => $household->id, 'survey_date' => today()->subDays(40)->toDateString()]);
        $new = HouseholdSurvey::factory()->create(['household_id' => $household->id, 'survey_date' => today()->toDateString()]);
        HouseholdSurvey::factory()->create();   // another household's survey must not appear
        Customer::factory()->create(['household_id' => $household->id, 'name' => 'Ani Wijaya']);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('households.show', $household))
            ->assertOk()
            ->assertViewHas('surveys', fn ($surveys) => $surveys->pluck('id')->all() === [$new->id, $old->id])
            ->assertSee('Ani Wijaya');
    }

    public function test_the_current_status_is_shown_from_the_latest_validated_survey(): void
    {
        $admin = $this->userWithRole('admin');
        $household = Household::factory()->create();
        HouseholdSurvey::factory()->validated($admin)->create(['household_id' => $household->id, 'sorting_status' => 'sorting']);
        $household->refreshSortingStatus();

        $this->actingAs($admin)->get(route('households.show', $household))
            ->assertOk()->assertSee($household->fresh()->statusLabel());
    }

    public function test_the_detail_page_works_for_a_household_without_surveys(): void
    {
        $household = Household::factory()->create();

        $this->actingAs($this->userWithRole('officer'))->get(route('households.show', $household))->assertOk();
    }

    public function test_a_deleted_household_keeps_its_surveys_readable(): void
    {
        $household = Household::factory()->create();
        $survey = HouseholdSurvey::factory()->create(['household_id' => $household->id]);
        $household->delete();

        $this->actingAs($this->userWithRole('admin'))->get(route('household-surveys.show', $survey))->assertOk();
    }
}
