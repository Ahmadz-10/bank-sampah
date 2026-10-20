<?php

namespace Tests\Feature\Surveys;

use App\Models\Household;
use App\Models\HouseholdSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class SurveyValidationTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_officers_and_viewers_cannot_validate_reject_or_reopen(): void
    {
        $survey = HouseholdSurvey::factory()->create();

        foreach (['officer', 'viewer'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->post(route('household-surveys.approve', $survey))->assertForbidden();
            $this->actingAs($user)->post(route('household-surveys.reject', $survey), ['validation_notes' => 'x'])->assertForbidden();
            $this->actingAs($user)->post(route('household-surveys.reopen', $survey))->assertForbidden();
        }

        $this->assertSame('pending', $survey->fresh()->validation_status);
        $this->assertSame('unknown', $survey->household->fresh()->sorting_status);
    }

    public function test_validating_a_survey_updates_the_household_status(): void
    {
        $admin = $this->userWithRole('admin');
        $household = Household::factory()->create();
        $survey = HouseholdSurvey::factory()->create(['household_id' => $household->id, 'sorting_status' => 'sorting']);

        $this->actingAs($admin)
            ->post(route('household-surveys.approve', $survey), ['validation_notes' => 'Looks good.'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $survey->refresh();
        $this->assertSame('validated', $survey->validation_status);
        $this->assertSame($admin->id, $survey->validated_by);
        $this->assertNotNull($survey->validated_at);
        $this->assertSame('Looks good.', $survey->validation_notes);
        $this->assertSame('sorting', $household->fresh()->sorting_status);
    }

    public function test_super_admin_can_validate_too(): void
    {
        $survey = HouseholdSurvey::factory()->create(['sorting_status' => 'not_sorting']);

        $this->actingAs($this->userWithRole('super_admin'))->post(route('household-surveys.approve', $survey));

        $this->assertSame('not_sorting', $survey->household->fresh()->sorting_status);
    }

    public function test_the_newest_validated_survey_decides_the_status_even_if_an_older_one_is_validated_later(): void
    {
        $admin = $this->userWithRole('admin');
        $household = Household::factory()->create();

        $newer = HouseholdSurvey::factory()->create([
            'household_id' => $household->id, 'survey_date' => today()->toDateString(), 'sorting_status' => 'not_sorting',
        ]);
        $older = HouseholdSurvey::factory()->create([
            'household_id' => $household->id, 'survey_date' => today()->subDays(20)->toDateString(), 'sorting_status' => 'sorting',
        ]);

        $this->actingAs($admin)->post(route('household-surveys.approve', $newer));
        $this->assertSame('not_sorting', $household->fresh()->sorting_status);

        // Validating the OLDER survey afterwards must not overwrite the newer result.
        $this->actingAs($admin)->post(route('household-surveys.approve', $older));
        $this->assertSame('not_sorting', $household->fresh()->sorting_status);
    }

    public function test_rejecting_needs_notes_and_leaves_the_household_alone(): void
    {
        $admin = $this->userWithRole('admin');
        $survey = HouseholdSurvey::factory()->create(['sorting_status' => 'sorting']);

        $this->actingAs($admin)->post(route('household-surveys.reject', $survey), ['validation_notes' => ''])
            ->assertSessionHasErrors('validation_notes');
        $this->assertSame('pending', $survey->fresh()->validation_status);

        $this->actingAs($admin)->post(route('household-surveys.reject', $survey), ['validation_notes' => 'Photo is unclear.'])
            ->assertSessionHasNoErrors();

        $survey->refresh();
        $this->assertSame('rejected', $survey->validation_status);
        $this->assertSame('Photo is unclear.', $survey->validation_notes);
        $this->assertSame($admin->id, $survey->validated_by);
        $this->assertSame('unknown', $survey->household->fresh()->sorting_status);
    }

    public function test_only_pending_surveys_can_be_validated_or_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $validated = HouseholdSurvey::factory()->validated($admin)->create();
        $rejected = HouseholdSurvey::factory()->rejected($admin)->create();

        $this->actingAs($admin)->post(route('household-surveys.approve', $validated))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('household-surveys.reject', $validated), ['validation_notes' => 'x'])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('household-surveys.approve', $rejected))->assertSessionHas('error');

        $this->assertSame('validated', $validated->fresh()->validation_status);
        $this->assertSame('rejected', $rejected->fresh()->validation_status);
    }

    public function test_reopening_a_validated_survey_recalculates_the_household_status(): void
    {
        $admin = $this->userWithRole('admin');
        $household = Household::factory()->create();

        $older = HouseholdSurvey::factory()->validated($admin)->create([
            'household_id' => $household->id, 'survey_date' => today()->subDays(20)->toDateString(), 'sorting_status' => 'sorting',
        ]);
        $newer = HouseholdSurvey::factory()->validated($admin)->create([
            'household_id' => $household->id, 'survey_date' => today()->toDateString(), 'sorting_status' => 'not_sorting',
        ]);
        $household->refreshSortingStatus();
        $this->assertSame('not_sorting', $household->fresh()->sorting_status);

        // Undo the newest: status falls back to the older validated survey
        $this->actingAs($admin)->post(route('household-surveys.reopen', $newer))->assertSessionHas('status');
        $newer->refresh();
        $this->assertSame('pending', $newer->validation_status);
        $this->assertNull($newer->validated_by);
        $this->assertNull($newer->validation_notes);
        $this->assertSame('sorting', $household->fresh()->sorting_status);

        // Undo the last validated one: back to unknown
        $this->actingAs($admin)->post(route('household-surveys.reopen', $older));
        $this->assertSame('unknown', $household->fresh()->sorting_status);
    }

    public function test_a_reopened_survey_becomes_editable_again(): void
    {
        $admin = $this->userWithRole('admin');
        $survey = HouseholdSurvey::factory()->validated($admin)->create();

        $this->actingAs($admin)->get(route('household-surveys.edit', $survey))->assertForbidden();
        $this->actingAs($admin)->post(route('household-surveys.reopen', $survey));
        $this->actingAs($admin)->get(route('household-surveys.edit', $survey))->assertOk();
    }

    public function test_the_detail_page_shows_validation_controls_to_admins_only(): void
    {
        $survey = HouseholdSurvey::factory()->create();

        $this->actingAs($this->userWithRole('admin'))->get(route('household-surveys.show', $survey))
            ->assertOk()->assertSee(route('household-surveys.approve', $survey));

        $this->actingAs($this->userWithRole('officer'))->get(route('household-surveys.show', $survey))
            ->assertOk()->assertDontSee(route('household-surveys.approve', $survey));
    }

    public function test_refresh_sorting_status_ignores_pending_and_rejected_surveys(): void
    {
        $household = Household::factory()->create();
        HouseholdSurvey::factory()->create(['household_id' => $household->id, 'sorting_status' => 'sorting']);
        HouseholdSurvey::factory()->rejected()->create(['household_id' => $household->id, 'sorting_status' => 'sorting']);

        $household->refreshSortingStatus();

        $this->assertSame('unknown', $household->fresh()->sorting_status);
    }
}
