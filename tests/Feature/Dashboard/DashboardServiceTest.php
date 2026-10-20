<?php

namespace Tests\Feature\Dashboard;

use App\Models\Customer;
use App\Models\Household;
use App\Models\HouseholdSurvey;
use App\Models\MonthlyWasteReduction;
use App\Models\WasteCategory;
use App\Models\WasteDeposit;
use App\Models\WasteSale;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\Concerns\CreatesWasteData;
use Tests\TestCase;

/**
 * The numbers on the dashboard. "Now" is frozen to 15 Oct 2026 so "this month" is predictable.
 */
class DashboardServiceTest extends TestCase
{
    use CreatesUsersWithRoles;
    use CreatesWasteData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::create(2026, 10, 15, 12));
    }

    private function dashboard(string $role = 'admin'): array
    {
        return app(DashboardService::class)->forUser($this->userWithRole($role));
    }

    /** @param array<int, array{0: WasteCategory, 1: float|int, 2: float|int}> $lines */
    private function deposit(array $lines, string $date = '2026-10-10'): WasteDeposit
    {
        return WasteDeposit::factory()->withLines($lines)->create(['deposit_date' => $date]);
    }

    /** @param array<int, array{0: WasteCategory, 1: float|int, 2: float|int}> $lines */
    private function sale(array $lines, string $date = '2026-10-10'): WasteSale
    {
        return WasteSale::factory()->withLines($lines)->create(['sale_date' => $date]);
    }

    private function household(string $status): Household
    {
        $household = Household::factory()->create();
        $household->forceFill(['sorting_status' => $status])->save();

        return $household;
    }

    // ---- households and customers -------------------------------------------------------

    public function test_households_are_counted_by_sorting_status_and_deleted_ones_are_ignored(): void
    {
        $this->household(Household::STATUS_SORTING);
        $this->household(Household::STATUS_SORTING);
        $this->household(Household::STATUS_NOT_SORTING);
        $this->household(Household::STATUS_UNKNOWN);
        $this->household(Household::STATUS_SORTING)->delete();

        $this->assertSame(
            ['total' => 4, 'sorting' => 2, 'not_sorting' => 1, 'unknown' => 1],
            $this->dashboard()['households']
        );
    }

    public function test_customers_count_the_active_ones_and_the_registered_total(): void
    {
        Customer::factory()->count(2)->create();
        Customer::factory()->create(['is_active' => false]);
        Customer::factory()->create()->delete();

        $this->assertSame(['active' => 2, 'total' => 3], $this->dashboard()['customers']);
    }

    // ---- this month's totals ------------------------------------------------------------

    public function test_deposit_and_sale_totals_cover_this_month_only_and_skip_voided_ones(): void
    {
        $plastic = $this->category('PLS', 2000);

        $this->deposit([[$plastic, 10, 2000]], '2026-10-01');            // first day: counts
        $this->deposit([[$plastic, 5.5, 2000]], '2026-10-31');           // last day: counts
        $this->deposit([[$plastic, 99, 2000]], '2026-09-30');            // last month
        $this->deposit([[$plastic, 99, 2000]], '2026-11-01');            // next month
        $this->deposit([[$plastic, 99, 2000]], '2026-10-05')->delete();  // voided

        $this->sale([[$plastic, 4, 3000]], '2026-10-03');
        $this->sale([[$plastic, 77, 3000]], '2026-09-20');
        $this->sale([[$plastic, 77, 3000]], '2026-10-04')->delete();

        $data = $this->dashboard();

        $this->assertSame(2, $data['deposits']['count']);
        $this->assertEqualsWithDelta(15.5, $data['deposits']['weight'], 0.0001);
        $this->assertEqualsWithDelta(31000, $data['deposits']['amount'], 0.01);

        $this->assertSame(1, $data['sales']['count']);
        $this->assertEqualsWithDelta(4, $data['sales']['weight'], 0.0001);
        $this->assertEqualsWithDelta(12000, $data['sales']['amount'], 0.01);

        $this->assertSame('2026-10-01', $data['month_start']);
        $this->assertSame('2026-10-31', $data['month_end']);
    }

    public function test_stock_is_deposited_minus_sold_in_kg_and_ignores_voided_and_other_units(): void
    {
        $plastic = $this->category('PLS', 2000);
        $oil = $this->category('OIL', 5000, null, ['unit' => 'liter']);

        $this->deposit([[$plastic, 12, 2000]], '2026-10-02');
        $this->deposit([[$plastic, 8, 2000]], '2026-08-15');              // older months count towards stock
        $this->sale([[$plastic, 5, 3000]], '2026-10-03');
        $this->deposit([[$plastic, 100, 2000]], '2026-10-04')->delete();  // voided deposit
        $this->deposit([[$oil, 50, 5000]], '2026-10-04');                 // litres cannot be added to kg

        $this->assertEqualsWithDelta(15, $this->dashboard()['stock_kg'], 0.0001);
    }

    public function test_waste_per_category_is_this_month_biggest_first_top_eight_without_voided_deposits(): void
    {
        $categories = collect(range(1, 9))->map(fn (int $i) => $this->category('C'.$i, 1000));
        foreach ($categories as $i => $category) {
            $this->deposit([[$category, $i + 1, 1000]]);                  // weights 1..9
        }
        $this->deposit([[$categories[0], 500, 1000]], '2026-09-10');      // last month
        $this->deposit([[$categories[0], 500, 1000]])->delete();          // voided

        $rows = $this->dashboard()['categories'];

        $this->assertCount(8, $rows);
        $this->assertSame(['C9 waste', 9.0], [$rows[0]['name'], $rows[0]['kg']]);
        $this->assertSame(2.0, $rows[7]['kg']);                           // the 1 kg category is cut off
    }

    // ---- reduction chart, queues --------------------------------------------------------

    public function test_the_reduction_chart_has_twelve_months_with_gaps(): void
    {
        MonthlyWasteReduction::factory()->create(['year' => 2026, 'month' => 3, 'reduction_percentage' => 31.5, 'target_percentage' => 30]);
        MonthlyWasteReduction::factory()->create(['year' => 2025, 'month' => 4, 'reduction_percentage' => 99, 'target_percentage' => 99]);

        $chart = $this->dashboard()['reduction_chart'];

        $this->assertCount(12, $chart['labels']);
        $this->assertSame(31.5, $chart['series'][0]['data'][2]);
        $this->assertSame(30.0, $chart['series'][1]['data'][2]);
        $this->assertNull($chart['series'][0]['data'][3]);                // April 2025 is another year
    }

    public function test_recent_rows_are_the_newest_five_without_voided_ones_and_pending_surveys_are_counted(): void
    {
        $plastic = $this->category('PLS', 2000);

        foreach (range(1, 7) as $day) {
            $this->deposit([[$plastic, 1, 2000]], sprintf('2026-10-%02d', $day));
        }
        $this->deposit([[$plastic, 1, 2000]], '2026-10-14')->delete();
        $this->sale([[$plastic, 1, 3000]], '2026-10-08');

        HouseholdSurvey::factory()->count(2)->create(['validation_status' => HouseholdSurvey::PENDING]);
        HouseholdSurvey::factory()->create(['validation_status' => HouseholdSurvey::VALIDATED]);

        $data = $this->dashboard();

        $this->assertCount(5, $data['recent_deposits']);
        $this->assertSame('2026-10-07', $data['recent_deposits'][0]->deposit_date->toDateString());
        $this->assertCount(1, $data['recent_sales']);
        $this->assertSame(2, $data['pending_surveys']);
    }

    // ---- who sees what (decision D2) ----------------------------------------------------

    public function test_each_role_gets_only_its_blocks_and_hidden_data_is_not_even_loaded(): void
    {
        $matrix = [
            //                households customers transactions reduction recent surveys
            'super_admin' => [true, true, true, true, true, true],
            'admin'       => [true, true, true, true, true, true],
            'officer'     => [true, true, true, false, true, true],
            'viewer'      => [true, true, true, true, false, false],
        ];

        foreach ($matrix as $role => $expected) {
            $data = $this->dashboard($role);

            $this->assertSame($expected, array_values($data['show']), "blocks for {$role}");
        }

        $officer = $this->dashboard('officer');
        $this->assertArrayNotHasKey('reduction_chart', $officer);

        $viewer = $this->dashboard('viewer');
        $this->assertArrayNotHasKey('recent_deposits', $viewer);
        $this->assertArrayNotHasKey('recent_sales', $viewer);
        $this->assertArrayNotHasKey('pending_surveys', $viewer);
        $this->assertArrayHasKey('deposits', $viewer);
    }
}
