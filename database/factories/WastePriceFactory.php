<?php

namespace Database\Factories;

use App\Models\WasteCategory;
use App\Models\WastePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WastePrice> */
class WastePriceFactory extends Factory
{
    public function definition(): array
    {
        $buy = fake()->numberBetween(5, 80) * 100;

        return [
            'waste_category_id' => WasteCategory::factory(),
            'buy_price' => $buy,
            'sell_price' => (int) round($buy * 1.25),
            'effective_date' => today()->toDateString(),
        ];
    }
}
