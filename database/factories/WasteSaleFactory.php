<?php

namespace Database\Factories;

use App\Models\WasteCategory;
use App\Models\WasteSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WasteSale> */
class WasteSaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_name' => fake('id_ID')->company(),
            'buyer_contact' => fake()->numerify('08##########'),
            'officer_id' => null,
            'sale_date' => today()->toDateString(),
            'notes' => null,
        ];
    }

    /**
     * Each line: [WasteCategory, weight, price_per_unit]. Does NOT check stock.
     *
     * @param  array<int, array{0: WasteCategory, 1: float|int, 2: float|int}>  $lines
     */
    public function withLines(array $lines): static
    {
        return $this->afterCreating(function (WasteSale $sale) use ($lines) {
            foreach ($lines as [$category, $weight, $price]) {
                $sale->details()->create([
                    'waste_category_id' => $category->id,
                    'weight' => $weight,
                    'price_per_unit' => $price,
                    'subtotal' => round($weight * $price, 2),
                ]);
            }

            $sale->recalculateTotals();
        });
    }
}
