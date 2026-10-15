<?php

namespace Database\Seeders;

use App\Models\Rw;
use App\Models\User;
use App\Services\WeighingService;
use Illuminate\Database\Seeder;

/**
 * Fake RW weighings for the last three months so the sheet and the monthly summary have data.
 * LOCAL DEVELOPMENT ONLY. Run once:  php artisan db:seed --class=DemoWeighingSeeder
 */
class DemoWeighingSeeder extends Seeder
{
    public function run(WeighingService $weighings): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoWeighingSeeder skipped in production.');

            return;
        }

        $user = User::query()->first();

        if (! $user) {
            $this->command?->warn('No user found. Run the main seeder first.');

            return;
        }

        $rwIds = Rw::query()->where('is_active', true)->pluck('id');

        foreach ([2, 1, 0] as $monthsAgo) {
            $date = today()->startOfMonth()->subMonths($monthsAgo);
            $rows = [];

            foreach ($rwIds as $id) {
                $rows[$id] = [
                    'organic_kg_per_day' => fake()->numberBetween(60, 280),
                    'inorganic_kg_per_day' => fake()->numberBetween(30, 180),
                    'b3_kg_per_day' => fake()->numberBetween(0, 8),
                    'residue_kg_per_day' => fake()->numberBetween(50, 220),
                    'carts' => fake()->numberBetween(1, 4),
                    'collectors' => fake()->numberBetween(1, 6),
                    'pickup_frequency' => fake()->numberBetween(1, 7),
                ];
            }

            $weighings->saveSheet($date->year, $date->month, $rows, $user);
        }
    }
}
