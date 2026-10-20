<?php

namespace Database\Factories;

use App\Models\WasteCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WasteCategory> */
class WasteCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->words(2, true),
            'unit' => 'kg',
            'waste_group' => 'anorganik',
            'description' => null,
            'is_active' => true,
        ];
    }
}
