<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WastePrice;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;

/**
 * Fake deposits and sales so lists, filters, totals and stock have something to show.
 * Goes through TransactionService, so every record follows the real rules.
 *
 * Needs DemoDataSeeder (customers) and WasteCategorySeeder (categories) first.
 * LOCAL DEVELOPMENT ONLY. Run once:  php artisan db:seed --class=DemoTransactionSeeder
 */
class DemoTransactionSeeder extends Seeder
{
    public function run(TransactionService $transactions, StockService $stock): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoTransactionSeeder skipped in production.');

            return;
        }

        $officer = User::query()->whereHas('role', fn ($q) => $q->where('name', 'officer'))->first() ?? User::query()->first();
        $categories = WasteCategory::query()->where('is_active', true)->get();
        $customers = Customer::query()->where('is_active', true)->get();

        if (! $officer || $categories->isEmpty() || $customers->isEmpty()) {
            $this->command?->warn('Need at least one user, category and customer. Run the other demo seeders first.');

            return;
        }

        // Deposits are priced by date, so make sure a price exists far enough in the past.
        foreach ($categories as $category) {
            $hasOld = $category->prices()->whereDate('effective_date', '<=', today()->subDays(120))->exists();
            $current = $category->priceOn();

            if (! $hasOld && $current) {
                WastePrice::create([
                    'waste_category_id' => $category->id,
                    'buy_price' => max(100, round((float) $current->buy_price * 0.9, -2)),
                    'sell_price' => $current->sell_price !== null ? max(100, round((float) $current->sell_price * 0.9, -2)) : null,
                    'effective_date' => today()->subDays(120)->toDateString(),
                ]);
            }
        }

        foreach (range(1, 40) as $i) {
            $lines = $categories->random(min($categories->count(), fake()->numberBetween(1, 3)))
                ->map(fn ($c) => ['waste_category_id' => $c->id, 'weight' => fake()->numberBetween(5, 200) / 10])
                ->values()->all();

            $transactions->createDeposit([
                'customer_id' => $customers->random()->id,
                'deposit_date' => today()->subDays(fake()->numberBetween(0, 60))->toDateString(),
                'notes' => null,
                'lines' => $lines,
            ], $officer);
        }

        // A few sales that sell part of what is in stock.
        $levels = $stock->levels();
        foreach (range(1, 6) as $i) {
            $lines = [];
            foreach ($categories->shuffle()->take(2) as $category) {
                $available = (float) ($levels[$category->id] ?? 0);
                if ($available < 5) {
                    continue;
                }
                $reference = $category->priceOn();
                $lines[] = [
                    'waste_category_id' => $category->id,
                    'weight' => round($available * fake()->numberBetween(10, 30) / 100, 1),
                    'price_per_unit' => round((float) ($reference?->sell_price ?? ($reference?->buy_price ?? 1000) * 1.3), -2),
                ];
            }

            if ($lines === []) {
                continue;
            }

            $transactions->createSale([
                'buyer_name' => fake('id_ID')->company(),
                'buyer_contact' => fake()->numerify('08##########'),
                'sale_date' => today()->subDays(fake()->numberBetween(0, 30))->toDateString(),
                'notes' => null,
                'lines' => $lines,
            ], $officer);

            $levels = $stock->levels();      // refresh so the next sale respects what is left
        }
    }
}
