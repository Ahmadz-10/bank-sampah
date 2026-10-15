<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\HouseholdSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HouseholdSurvey> */
class HouseholdSurveyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'surveyed_by' => null,
            'survey_date' => today()->toDateString(),
            'sorting_status' => fake()->randomElement([Household::STATUS_SORTING, Household::STATUS_NOT_SORTING]),
            'notes' => null,
            'validation_status' => HouseholdSurvey::PENDING,
        ];
    }

    /** Note: this does not update the household. Call $household->refreshSortingStatus() after. */
    public function validated(?User $by = null): static
    {
        return $this->state(fn () => [
            'validation_status' => HouseholdSurvey::VALIDATED,
            'validated_by' => $by?->id,
            'validated_at' => now(),
        ]);
    }

    public function rejected(?User $by = null, string $notes = 'Photo is unclear.'): static
    {
        return $this->state(fn () => [
            'validation_status' => HouseholdSurvey::REJECTED,
            'validated_by' => $by?->id,
            'validated_at' => now(),
            'validation_notes' => $notes,
        ]);
    }
}
