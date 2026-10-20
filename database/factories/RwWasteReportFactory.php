<?php

namespace Database\Factories;

use App\Models\Rw;
use App\Models\RwWasteReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RwWasteReport> */
class RwWasteReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rw_id' => Rw::query()->inRandomOrder()->value('id'),
            'year' => (int) today()->format('Y'),
            'month' => (int) today()->format('n'),
            'organic_kg_per_day' => fake()->numberBetween(50, 300),
            'inorganic_kg_per_day' => fake()->numberBetween(30, 200),
            'b3_kg_per_day' => fake()->numberBetween(0, 10),
            'residue_kg_per_day' => fake()->numberBetween(40, 250),
            'carts' => fake()->numberBetween(1, 4),
            'collectors' => fake()->numberBetween(1, 6),
            'pickup_frequency' => fake()->numberBetween(1, 7),
            'days_counted' => 30,
        ];
    }
}
