<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Rw;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Household> */
class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'head_name' => fake('id_ID')->name(),
            'address' => fake('id_ID')->streetAddress(),
            'rt' => str_pad((string) fake()->numberBetween(1, 12), 3, '0', STR_PAD_LEFT),
            'rw_id' => Rw::query()->inRandomOrder()->value('id'),
            'phone' => fake()->numerify('08##########'),
            'member_count' => fake()->numberBetween(1, 8),
        ];
    }
}
