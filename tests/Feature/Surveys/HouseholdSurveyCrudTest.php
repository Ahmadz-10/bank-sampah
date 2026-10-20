<?php

namespace Tests\Feature\Surveys;

use App\Models\Household;
use App\Models\HouseholdSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class HouseholdSurveyCrudTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function validData(Household $household, array $overrides = []): array
    {
        return array_merge([
            'household_id' => $household->id,
            'survey_date' => today()->toDateString(),
            'sorting_status' => 'sorting',
            'notes' => 'Has separate bins.',
        ], $overrides);
    }

    // ---- access -----------------------------------------------------------------------

    public function test_viewer_cannot_use_surveys(): void
    {
        $viewer = $this->userWithRole('viewer');
        $household = Household::factory()->create();

        $this->actingAs($viewer)->get(route('household-surveys.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('household-surveys.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('household-surveys.store'), $this->validData($household))->assertForbidden();
        $this->assertDatabaseCount('household_surveys', 0);
    }

    public function test_officer_and_admin_can_open_the_list(): void
    {
        HouseholdSurvey::factory()->count(2)->create();

        foreach (['officer', 'admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('household-surveys.index'))
                ->assertOk()
                ->assertViewHas('surveys');
        }
    }

    // ---- create -----------------------------------------------------------------------

    public function test_officer_creates_a_pending_survey_that_does_not_change_the_household(): void
    {
        $officer = $this->userWithRole('officer');
        $household = Household::factory()->create();

        $response = $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household));

        $survey = HouseholdSurvey::firstOrFail();
        $response->assertRedirect(route('household-surveys.show', $survey));

        $this->assertSame('pending', $survey->validation_status);
        $this->assertSame($officer->id, $survey->surveyed_by);
        $this->assertSame('sorting', $survey->sorting_status);
        $this->assertSame('unknown', $household->fresh()->sorting_status);   // only validation changes it
    }

    public function test_a_request_cannot_validate_its_own_survey(): void
    {
        $household = Household::factory()->create();

        $this->actingAs($this->userWithRole('officer'))->post(route('household-surveys.store'), $this->validData($household, [
            'validation_status' => 'validated',
            'validated_by' => 1,
            'surveyed_by' => 999,
        ]));

        $survey = HouseholdSurvey::firstOrFail();
        $this->assertSame('pending', $survey->validation_status);
        $this->assertNull($survey->validated_by);
        $this->assertNotSame(999, $survey->surveyed_by);
    }

    public function test_validation_rules(): void
    {
        $officer = $this->userWithRole('officer');
        $household = Household::factory()->create();
        $deleted = Household::factory()->create();
        $deleted->delete();

        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, ['survey_date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('survey_date');

        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, ['sorting_status' => 'maybe']))
            ->assertSessionHasErrors('sorting_status');

        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, ['sorting_status' => 'unknown']))
            ->assertSessionHasErrors('sorting_status');

        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, ['household_id' => 9999]))
            ->assertSessionHasErrors('household_id');

        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($deleted))
            ->assertSessionHasErrors('household_id');

        $this->assertDatabaseCount('household_surveys', 0);
    }

    public function test_the_create_form_preselects_the_household_from_the_link(): void
    {
        $household = Household::factory()->create();

        $this->actingAs($this->userWithRole('officer'))
            ->get(route('household-surveys.create', ['household' => $household->id]))
            ->assertOk()
            ->assertViewHas('survey', fn ($survey) => $survey->household_id === $household->id);
    }

    // ---- photos -----------------------------------------------------------------------

    public function test_a_photo_is_stored_on_the_private_disk(): void
    {
        $household = Household::factory()->create();

        $this->actingAs($this->userWithRole('officer'))->post(route('household-surveys.store'), $this->validData($household, [
            'photo' => UploadedFile::fake()->image('door.jpg', 600, 400),
        ]))->assertSessionHasNoErrors();

        $survey = HouseholdSurvey::firstOrFail();
        $this->assertNotNull($survey->photo_path);
        Storage::disk('local')->assertExists($survey->photo_path);
    }

    public function test_only_small_images_are_accepted(): void
    {
        $officer = $this->userWithRole('officer');
        $household = Household::factory()->create();

        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, [
            'photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('photo');

        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, [
            'photo' => UploadedFile::fake()->image('huge.jpg')->size(5000),
        ]))->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('household_surveys', 0);
    }

    public function test_the_photo_is_served_only_to_users_who_may_see_surveys(): void
    {
        $household = Household::factory()->create();
        $this->actingAs($this->userWithRole('officer'))->post(route('household-surveys.store'), $this->validData($household, [
            'photo' => UploadedFile::fake()->image('door.jpg'),
        ]));
        $survey = HouseholdSurvey::firstOrFail();

        $this->actingAs($this->userWithRole('admin'))->get(route('household-surveys.photo', $survey))->assertOk();
        $this->actingAs($this->userWithRole('viewer'))->get(route('household-surveys.photo', $survey))->assertForbidden();
    }

    public function test_guests_cannot_fetch_a_photo(): void
    {
        $survey = HouseholdSurvey::factory()->create(['photo_path' => 'survey-photos/x.jpg']);

        $this->get(route('household-surveys.photo', $survey))->assertRedirect(route('login'));
    }

    public function test_a_survey_without_a_photo_returns_404_from_the_photo_route(): void
    {
        $survey = HouseholdSurvey::factory()->create();

        $this->actingAs($this->userWithRole('admin'))->get(route('household-surveys.photo', $survey))->assertNotFound();
    }

    // ---- edit / ownership -------------------------------------------------------------

    public function test_an_officer_can_edit_their_own_pending_survey(): void
    {
        $officer = $this->userWithRole('officer');
        $survey = HouseholdSurvey::factory()->create(['surveyed_by' => $officer->id]);

        $this->actingAs($officer)->get(route('household-surveys.edit', $survey))->assertOk();

        $this->actingAs($officer)->put(route('household-surveys.update', $survey), [
            'survey_date' => today()->toDateString(), 'sorting_status' => 'not_sorting', 'notes' => 'Changed my mind.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('household-surveys.show', $survey));

        $survey->refresh();
        $this->assertSame('not_sorting', $survey->sorting_status);
        $this->assertSame('Changed my mind.', $survey->notes);
    }

    public function test_an_officer_cannot_edit_or_delete_another_officers_survey(): void
    {
        $owner = $this->userWithRole('officer');
        $other = $this->userWithRole('officer');
        $survey = HouseholdSurvey::factory()->create(['surveyed_by' => $owner->id]);

        $this->actingAs($other)->get(route('household-surveys.edit', $survey))->assertForbidden();
        $this->actingAs($other)->put(route('household-surveys.update', $survey), [
            'survey_date' => today()->toDateString(), 'sorting_status' => 'sorting',
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('household-surveys.destroy', $survey))->assertForbidden();

        $this->assertModelExists($survey);
    }

    public function test_an_admin_can_edit_any_unvalidated_survey(): void
    {
        $officer = $this->userWithRole('officer');
        $survey = HouseholdSurvey::factory()->create(['surveyed_by' => $officer->id]);

        $this->actingAs($this->userWithRole('admin'))->put(route('household-surveys.update', $survey), [
            'survey_date' => today()->toDateString(), 'sorting_status' => 'not_sorting',
        ])->assertSessionHasNoErrors();

        $this->assertSame('not_sorting', $survey->fresh()->sorting_status);
    }

    public function test_the_household_of_a_survey_cannot_be_changed(): void
    {
        $officer = $this->userWithRole('officer');
        $survey = HouseholdSurvey::factory()->create(['surveyed_by' => $officer->id]);
        $other = Household::factory()->create();

        $this->actingAs($officer)->put(route('household-surveys.update', $survey), [
            'household_id' => $other->id, 'survey_date' => today()->toDateString(), 'sorting_status' => 'sorting',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($other->id, $survey->fresh()->household_id);
    }

    public function test_replacing_or_removing_a_photo_deletes_the_old_file(): void
    {
        $officer = $this->userWithRole('officer');
        $household = Household::factory()->create();
        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, [
            'photo' => UploadedFile::fake()->image('one.jpg'),
        ]));
        $survey = HouseholdSurvey::firstOrFail();
        $first = $survey->photo_path;

        $this->actingAs($officer)->put(route('household-surveys.update', $survey), [
            'survey_date' => today()->toDateString(), 'sorting_status' => 'sorting',
            'photo' => UploadedFile::fake()->image('two.jpg'),
        ])->assertSessionHasNoErrors();

        $survey->refresh();
        $second = $survey->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->actingAs($officer)->put(route('household-surveys.update', $survey), [
            'survey_date' => today()->toDateString(), 'sorting_status' => 'sorting', 'remove_photo' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNull($survey->fresh()->photo_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_editing_a_rejected_survey_resubmits_it(): void
    {
        $officer = $this->userWithRole('officer');
        $admin = $this->userWithRole('admin');
        $survey = HouseholdSurvey::factory()->rejected($admin, 'Photo is unclear.')->create(['surveyed_by' => $officer->id]);

        $this->actingAs($officer)->put(route('household-surveys.update', $survey), [
            'survey_date' => today()->toDateString(), 'sorting_status' => 'sorting',
        ])->assertSessionHasNoErrors();

        $survey->refresh();
        $this->assertSame('pending', $survey->validation_status);
        $this->assertNull($survey->validated_by);
        $this->assertNull($survey->validated_at);
        $this->assertNull($survey->validation_notes);
    }

    // ---- delete / lock ----------------------------------------------------------------

    public function test_deleting_a_pending_survey_also_deletes_its_photo(): void
    {
        $officer = $this->userWithRole('officer');
        $household = Household::factory()->create();
        $this->actingAs($officer)->post(route('household-surveys.store'), $this->validData($household, [
            'photo' => UploadedFile::fake()->image('door.jpg'),
        ]));
        $survey = HouseholdSurvey::firstOrFail();
        $path = $survey->photo_path;

        $this->actingAs($officer)->delete(route('household-surveys.destroy', $survey))
            ->assertRedirect(route('household-surveys.index'));

        $this->assertModelMissing($survey);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_validated_surveys_are_locked_for_everyone(): void
    {
        $officer = $this->userWithRole('officer');
        $survey = HouseholdSurvey::factory()->validated()->create(['surveyed_by' => $officer->id]);

        foreach (['officer' => $officer, 'admin' => $this->userWithRole('admin'), 'super_admin' => $this->userWithRole('super_admin')] as $user) {
            $this->actingAs($user)->get(route('household-surveys.edit', $survey))->assertForbidden();
            $this->actingAs($user)->put(route('household-surveys.update', $survey), [
                'survey_date' => today()->toDateString(), 'sorting_status' => 'not_sorting',
            ])->assertForbidden();
            $this->actingAs($user)->delete(route('household-surveys.destroy', $survey))->assertForbidden();
        }

        $this->assertModelExists($survey);
        $this->assertSame('validated', $survey->fresh()->validation_status);
    }

    // ---- list filters -----------------------------------------------------------------

    public function test_the_list_can_be_filtered(): void
    {
        $budi = Household::factory()->create(['head_name' => 'Budi Santoso']);
        $siti = Household::factory()->create(['head_name' => 'Siti Aminah']);
        HouseholdSurvey::factory()->create(['household_id' => $budi->id, 'survey_date' => today()->subDays(30)->toDateString(), 'sorting_status' => 'sorting']);
        HouseholdSurvey::factory()->validated()->create(['household_id' => $siti->id, 'survey_date' => today()->toDateString(), 'sorting_status' => 'not_sorting']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('household-surveys.index', ['q' => 'Budi']))
            ->assertOk()->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');

        $this->actingAs($admin)->get(route('household-surveys.index', ['status' => 'validated']))
            ->assertOk()->assertSee('Siti Aminah')->assertDontSee('Budi Santoso');

        $this->actingAs($admin)->get(route('household-surveys.index', ['sorting' => 'sorting']))
            ->assertOk()->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');

        $this->actingAs($admin)->get(route('household-surveys.index', ['from' => today()->subDays(5)->toDateString()]))
            ->assertOk()->assertSee('Siti Aminah')->assertDontSee('Budi Santoso');

        $this->actingAs($admin)->get(route('household-surveys.index', ['to' => today()->subDays(5)->toDateString()]))
            ->assertOk()->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');
    }
}
