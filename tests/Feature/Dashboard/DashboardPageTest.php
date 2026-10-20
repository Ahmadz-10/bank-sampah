<?php

namespace Tests\Feature\Dashboard;

use App\Models\Customer;
use App\Models\Household;
use App\Models\HouseholdSurvey;
use App\Models\MonthlyWasteReduction;
use App\Models\WasteDeposit;
use App\Models\WasteSale;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\Concerns\CreatesWasteData;
use Tests\TestCase;

/**
 * The dashboard page as each role sees it, and the check that its totals agree with the list pages.
 * "Now" is frozen to 15 Oct 2026.
 */
class DashboardPageTest extends TestCase
{
    use CreatesUsersWithRoles;
    use CreatesWasteData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::create(2026, 10, 15, 12));
        app()->setLocale('en');
    }

    private function household(string $status): Household
    {
        $household = Household::factory()->create();
        $household->forceFill(['sorting_status' => $status])->save();

        return $household;
    }

    // ---- what each role sees ------------------------------------------------------------

    public function test_pimpinan_sees_totals_and_charts_but_no_rows_or_queues(): void
    {
        $plastic = $this->category('PLS', 2000);
        $customer = Customer::factory()->create(['name' => 'Secret Customer']);
        $deposit = WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create(['customer_id' => $customer->id, 'deposit_date' => '2026-10-10']);
        MonthlyWasteReduction::factory()->create(['year' => 2026, 'month' => 9, 'reduction_percentage' => 30, 'target_percentage' => 25]);
        $this->household(Household::STATUS_SORTING);

        $this->actingAs($this->userWithRole('viewer'))->get('/dashboard')
            ->assertOk()
            ->assertSee('Deposits this month')
            ->assertSee('Reduction vs target')
            ->assertSee('Sorting households')
            ->assertSee('Waste deposited by category')
            ->assertDontSee('Recent deposits')
            ->assertDontSee('Pending surveys')
            ->assertDontSee('Secret Customer')
            ->assertDontSee($deposit->deposit_number)
            // The cards are not links: Pimpinan cannot open those pages.
            ->assertDontSee(route('waste-deposits.index'), false)
            ->assertDontSee(route('households.index'), false);
    }

    public function test_petugas_sees_the_work_queues_but_not_the_reduction_chart(): void
    {
        $plastic = $this->category('PLS', 2000);
        $deposit = WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create(['deposit_date' => '2026-10-10']);
        HouseholdSurvey::factory()->create(['validation_status' => HouseholdSurvey::PENDING]);

        $this->actingAs($this->userWithRole('officer'))->get('/dashboard')
            ->assertOk()
            ->assertSee('Recent deposits')
            ->assertSee('Recent sales')
            ->assertSee('Pending surveys')
            ->assertSee($deposit->deposit_number)
            ->assertDontSee('Reduction vs target')
            // The deposits card opens the Deposits list on this month's range.
            ->assertSee('from=2026-10-01', false)
            ->assertSee('to=2026-10-31', false);
    }

    public function test_admin_and_super_admin_see_everything(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/dashboard')
                ->assertOk()
                ->assertSee('Deposits this month')
                ->assertSee('Sales this month')
                ->assertSee('Stock in hand')
                ->assertSee('Active customers')
                ->assertSee('Pending surveys')
                ->assertSee('Reduction vs target')
                ->assertSee('Recent deposits');
        }
    }

    public function test_an_empty_system_shows_zeros_and_messages_instead_of_empty_charts(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/dashboard')
            ->assertOk()
            ->assertSee('No deposits yet.')
            ->assertSee('No sales yet.')
            ->assertSee('No households recorded yet.')
            ->assertSee('No deposits this month.')
            ->assertSee('No data to chart yet.')
            ->assertDontSee('<canvas', false);
    }

    public function test_charts_are_drawn_once_there_is_data(): void
    {
        $plastic = $this->category('PLS', 2000);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create(['deposit_date' => '2026-10-10']);
        MonthlyWasteReduction::factory()->create(['year' => 2026, 'month' => 9, 'reduction_percentage' => 30, 'target_percentage' => 25]);
        $this->household(Household::STATUS_SORTING);

        $html = $this->actingAs($this->userWithRole('admin'))->get('/dashboard')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '<canvas'));                 // line, doughnut, bar
        foreach (['line', 'doughnut', 'bar'] as $type) {
            $this->assertStringContainsString('&quot;type&quot;:&quot;'.$type.'&quot;', $html);
        }
    }

    // ---- P6-SH-2: dashboard totals against the list pages -------------------------------

    public function test_dashboard_totals_agree_with_the_list_pages(): void
    {
        $plastic = $this->category('PLS', 2000);
        $paper = $this->category('KRT', 1000);

        // households: 2 sorting, 1 not sorting, 1 not surveyed (+1 deleted)
        $this->household(Household::STATUS_SORTING);
        $this->household(Household::STATUS_SORTING);
        $this->household(Household::STATUS_NOT_SORTING);
        $this->household(Household::STATUS_UNKNOWN);
        $this->household(Household::STATUS_SORTING)->delete();

        // customers: 2 active, 1 inactive
        Customer::factory()->count(2)->create();
        Customer::factory()->create(['is_active' => false]);

        // deposits and sales: some this month, some in other months, some voided
        WasteDeposit::factory()->withLines([[$plastic, 12.5, 2000], [$paper, 3, 1000]])->create(['deposit_date' => '2026-10-02']);
        WasteDeposit::factory()->withLines([[$plastic, 7, 2000]])->create(['deposit_date' => '2026-10-31']);
        WasteDeposit::factory()->withLines([[$plastic, 50, 2000]])->create(['deposit_date' => '2026-09-30']);
        WasteDeposit::factory()->withLines([[$plastic, 50, 2000]])->create(['deposit_date' => '2026-10-09'])->delete();
        WasteSale::factory()->withLines([[$plastic, 6, 3000]])->create(['sale_date' => '2026-10-12']);
        WasteSale::factory()->withLines([[$plastic, 6, 3000]])->create(['sale_date' => '2026-08-12']);
        WasteSale::factory()->withLines([[$plastic, 6, 3000]])->create(['sale_date' => '2026-10-13'])->delete();

        $admin = $this->userWithRole('admin');
        $dash = app(DashboardService::class)->forUser($admin);
        $range = ['from' => $dash['month_start'], 'to' => $dash['month_end']];

        // Households page, filtered by status.
        $total = fn (array $query, string $route, string $key) => $this->actingAs($admin)->get(route($route, $query))->assertOk()->viewData($key);

        $this->assertSame($dash['households']['total'], $total([], 'households.index', 'households')->total());
        $this->assertSame($dash['households']['sorting'], $total(['status' => 'sorting'], 'households.index', 'households')->total());
        $this->assertSame($dash['households']['not_sorting'], $total(['status' => 'not_sorting'], 'households.index', 'households')->total());
        $this->assertSame($dash['households']['unknown'], $total(['status' => 'unknown'], 'households.index', 'households')->total());

        // Customers page.
        $this->assertSame($dash['customers']['active'], $total(['status' => 'active'], 'customers.index', 'customers')->total());
        $this->assertSame($dash['customers']['total'], $total([], 'customers.index', 'customers')->total());

        // Deposits and Sales pages: the totals bar for this month's range.
        $deposits = $total($range, 'waste-deposits.index', 'totals');
        $this->assertSame($dash['deposits']['count'], (int) $deposits->cnt);
        $this->assertEqualsWithDelta($dash['deposits']['weight'], (float) $deposits->weight, 0.0001);
        $this->assertEqualsWithDelta($dash['deposits']['amount'], (float) $deposits->amount, 0.01);

        $sales = $total($range, 'waste-sales.index', 'totals');
        $this->assertSame($dash['sales']['count'], (int) $sales->cnt);
        $this->assertEqualsWithDelta($dash['sales']['weight'], (float) $sales->weight, 0.0001);
        $this->assertEqualsWithDelta($dash['sales']['amount'], (float) $sales->amount, 0.01);

        // Sanity: the numbers are the ones this data should give, not just equal to each other.
        $this->assertSame(2, $dash['deposits']['count']);
        $this->assertEqualsWithDelta(22.5, $dash['deposits']['weight'], 0.0001);
        $this->assertSame(1, $dash['sales']['count']);
    }
}
