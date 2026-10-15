<?php

namespace Tests\Concerns;

use App\Models\WasteCategory;
use App\Models\WastePrice;

trait CreatesWasteData
{
    /**
     * A category with one price that has been in effect for 30 days.
     * Extra attributes (e.g. ['is_active' => false]) go to the category.
     */
    protected function category(string $code, float $buy, ?float $sell = null, array $attributes = []): WasteCategory
    {
        $category = WasteCategory::factory()->create(array_merge([
            'code' => $code,
            'name' => ucfirst(strtolower($code)).' waste',
        ], $attributes));

        WastePrice::factory()->create([
            'waste_category_id' => $category->id,
            'buy_price' => $buy,
            'sell_price' => $sell,
            'effective_date' => today()->subDays(30)->toDateString(),
        ]);

        return $category;
    }
}
