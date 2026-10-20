<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fake households and customers so the list pages, search and pagination have data.
 * LOCAL DEVELOPMENT ONLY. Run once:  php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoDataSeeder skipped in production.');

            return;
        }

        $createdBy = User::query()->value('id');

        Household::factory()->count(30)->create(['created_by' => $createdBy]);

        // About two thirds of the households have a registered customer.
        Household::query()->inRandomOrder()->take(20)->get()->each(function (Household $household) {
            Customer::factory()->create([
                'household_id' => $household->id,
                'name' => $household->head_name,
                'address' => $household->address,
            ]);
        });
    }
}
