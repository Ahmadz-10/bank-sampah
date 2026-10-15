<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\WasteCategory;
use App\Models\WasteDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WasteDeposit> */
class WasteDepositFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'officer_id' => null,
            'deposit_date' => today()->toDateString(),
            'notes' => null,
        ];
    }

    /**
     * Add lines and recalculate totals. Each line: [WasteCategory, weight, price_per_unit].
     * Bypasses the service on purpose, so tests can set any price they like.
     *
     * @param  array<int, array{0: WasteCategory, 1: float|int, 2: float|int}>  $lines
     */
    public function withLines(array $lines): static
    {
        return $this->afterCreating(function (WasteDeposit $deposit) use ($lines) {
            foreach ($lines as [$category, $weight, $price]) {
                $deposit->details()->create([
                    'waste_category_id' => $category->id,
                    'weight' => $weight,
                    'price_per_unit' => $price,
                    'subtotal' => round($weight * $price, 2),
                ]);
            }

            $deposit->recalculateTotals();
        });
    }
}
