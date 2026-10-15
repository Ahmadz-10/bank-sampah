<?php

namespace Database\Factories;

use App\Models\Rw;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rw> RW 01-11 already exist (created by the migration); this makes extra ones. */
class RwFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(12, 99),
            'chairman_name' => fake('id_ID')->name(),
            'household_count' => fake()->numberBetween(80, 600),
            'is_active' => true,
        ];
    }
}
