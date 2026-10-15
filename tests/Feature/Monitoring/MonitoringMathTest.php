<?php

namespace Tests\Feature\Monitoring;

use App\Models\RwWasteReport;
use App\Services\MonitoringService;
use Tests\TestCase;

/** Pure calculations: no database needed. */
class MonitoringMathTest extends TestCase
{
    private function report(array $overrides = []): RwWasteReport
    {
        return new RwWasteReport(array_merge([
            'organic_kg_per_day' => 100, 'inorganic_kg_per_day' => 50, 'b3_kg_per_day' => 2, 'residue_kg_per_day' => 80,
            'pickup_frequency' => 1, 'days_counted' => 30,
        ], $overrides));
    }

    public function test_percentage_is_diverted_over_total_and_safe_when_nothing_was_generated(): void
    {
        $this->assertSame(30.0, MonitoringService::percentage(3000, 10000));
        $this->assertSame(33.33, MonitoringService::percentage(1, 3));
        $this->assertSame(100.0, MonitoringService::percentage(500, 500));
        $this->assertSame(0.0, MonitoringService::percentage(0, 1000));
        $this->assertSame(0.0, MonitoringService::percentage(0, 0));       // no division by zero
        $this->assertSame(0.0, MonitoringService::percentage(50, 0));
    }

    public function test_total_per_day_is_the_sum_of_the_four_groups(): void
    {
        $this->assertSame(232.0, $this->report()->totalPerDay());
        $this->assertSame(150.0, $this->report()->groupsPerDay(['organik', 'anorganik']));
        $this->assertSame(80.0, $this->report()->groupsPerDay('residu'));
        $this->assertSame(0.0, $this->report()->groupsPerDay([]));
    }

    public function test_monthly_kg_multiplies_by_the_days_counted(): void
    {
        $this->assertSame(6960.0, $this->report()->monthlyKg());                       // 232 x 30
        $this->assertSame(4500.0, $this->report()->monthlyKg(['organik', 'anorganik'])); // 150 x 30
        $this->assertSame(4200.0, $this->report(['days_counted' => 28])->monthlyKg(['organik', 'anorganik']));   // 150 x 28
    }

    public function test_pickup_frequency_is_information_only_and_does_not_inflate_the_transported_weight(): void
    {
        // The original workbook multiplied by frequency (30 x 30 = 900x). We must not.
        $once = $this->report(['pickup_frequency' => 1])->transportedKg();
        $often = $this->report(['pickup_frequency' => 30])->transportedKg();

        $this->assertSame(6960.0, $once);
        $this->assertSame($once, $often);
    }

    public function test_decimals_do_not_drift(): void
    {
        $report = $this->report(['organic_kg_per_day' => 0.1, 'inorganic_kg_per_day' => 0.2, 'b3_kg_per_day' => 0, 'residue_kg_per_day' => 0, 'days_counted' => 10]);

        $this->assertSame(3.0, $report->monthlyKg());     // (0.1 + 0.2) x 10, rounded to 2 decimals
    }
}
