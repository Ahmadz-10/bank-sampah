<?php

namespace Database\Factories;

use App\Models\MonthlyWasteReduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonthlyWasteReduction> */
class MonthlyWasteReductionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year' => (int) today()->format('Y'),
            'month' => fake()->unique()->numberBetween(1, 12),
            'total_waste_generated' => 10000,
            'waste_diverted' => 3000,
            'waste_to_tps' => 6500,
            'reduction_percentage' => 30,
            'target_percentage' => 25,
        ];
    }
}
