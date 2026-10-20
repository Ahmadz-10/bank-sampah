<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\HouseholdSurvey;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fake survey history for existing households (run DemoDataSeeder first).
 * LOCAL DEVELOPMENT ONLY. Run once:  php artisan db:seed --class=DemoSurveySeeder
 */
class DemoSurveySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSurveySeeder skipped in production.');

            return;
        }

        $officer = User::query()->whereHas('role', fn ($q) => $q->where('name', 'officer'))->first();
        $admin = User::query()->whereHas('role', fn ($q) => $q->where('name', 'admin'))->first();

        if (Household::query()->doesntExist()) {
            $this->command?->warn('No households found. Run DemoDataSeeder first.');

            return;
        }

        Household::query()->inRandomOrder()->take(20)->get()->each(function (Household $household) use ($officer, $admin) {
            // Oldest to newest, so the newest validated survey decides the household status.
            foreach ([90, 45, 7] as $daysAgo) {
                if (fake()->boolean(35)) {
                    continue;
                }

                $factory = HouseholdSurvey::factory();
                $factory = match (fake()->randomElement(['validated', 'validated', 'rejected', 'pending'])) {
                    'validated' => $factory->validated($admin),
                    'rejected' => $factory->rejected($admin),
                    default => $factory,
                };

                $factory->create([
                    'household_id' => $household->id,
                    'surveyed_by' => $officer?->id,
                    'survey_date' => today()->subDays($daysAgo)->toDateString(),
                ]);
            }

            $household->refreshSortingStatus();
        });
    }
}
