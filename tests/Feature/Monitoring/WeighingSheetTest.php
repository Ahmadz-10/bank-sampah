<?php

namespace Tests\Feature\Monitoring;

use App\Models\MonthlyWasteReduction;
use App\Models\Rw;
use App\Models\RwWasteReport;
use App\Models\WasteDeposit;
use App\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\Concerns\CreatesWasteData;
use Tests\TestCase;

class WeighingSheetTest extends TestCase
{
    use CreatesUsersWithRoles;
    use CreatesWasteData;
    use RefreshDatabase;

    private const YEAR = 2026;
    private const MONTH = 4;      // April 2026: 30 days, safely in the past

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function rw(int $number): int
    {
        return Rw::where('number', $number)->value('id');
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'organic_kg_per_day' => 100, 'inorganic_kg_per_day' => 50, 'b3_kg_per_day' => 2, 'residue_kg_per_day' => 80,
            'carts' => 3, 'collectors' => 5, 'pickup_frequency' => 7, 'days_counted' => 30, 'notes' => '',
        ], $overrides);
    }

    // ---- access -----------------------------------------------------------------------

    public function test_the_rw_reference_data_exists_after_migrating(): void
    {
        $this->assertSame(11, Rw::count());
        $this->assertSame(range(1, 11), Rw::orderBy('number')->pluck('number')->all());
        $this->assertSame('RW 01', Rw::where('number', 1)->first()->name);
        $this->assertSame('RW 11', Rw::where('number', 11)->first()->name);
    }

    public function test_only_admins_and_officers_can_use_the_sheet(): void
    {
        foreach (['officer', 'admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('waste-weighings.index'))->assertOk();
        }

        $this->actingAs($this->userWithRole('viewer'))->get(route('waste-weighings.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('viewer'))->post(route('waste-weighings.store'), [])->assertForbidden();
    }

    public function test_the_sheet_lists_all_eleven_rws_and_defaults_to_this_month(): void
    {
        $this->actingAs($this->userWithRole('officer'))
            ->get(route('waste-weighings.index'))
            ->assertOk()
            ->assertViewHas('rws', fn ($rws) => $rws->count() === 11)
            ->assertViewHas('year', (int) today()->format('Y'))
            ->assertSee('RW 01')->assertSee('RW 11');
    }

    public function test_a_garbage_period_falls_back_to_this_month(): void
    {
        $this->actingAs($this->userWithRole('officer'))
            ->get(route('waste-weighings.index', ['year' => 'x', 'month' => 99]))
            ->assertOk()
            ->assertViewHas('month', (int) today()->format('n'));
    }

    // ---- saving -----------------------------------------------------------------------

    public function test_saving_the_sheet_stores_the_rows_and_builds_the_monthly_figures(): void
    {
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)->post(route('waste-weighings.store'), [
            'year' => self::YEAR, 'month' => self::MONTH,
            'rows' => [
                $this->rw(1) => $this->row(),
                $this->rw(2) => $this->row([
                    'organic_kg_per_day' => 20,
                    'inorganic_kg_per_day' => 10,
                    'b3_kg_per_day' => 0,
                    'residue_kg_per_day' => 70,
                ]),
                $this->rw(3) => $this->row([
                    'organic_kg_per_day' => '',
                    'inorganic_kg_per_day' => '',
                    'b3_kg_per_day' => '',
                    'residue_kg_per_day' => '',
                    'carts' => '',
                    'collectors' => '',
                    'pickup_frequency' => '',
                ]),
            ],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('waste-weighings.index', [
                'year' => self::YEAR,
                'month' => self::MONTH,
            ]));

        $this->assertSame(2, RwWasteReport::count()); // Empty RW 03 is not stored.

        $report = RwWasteReport::where('rw_id', $this->rw(1))->firstOrFail();

        $this->assertSame($officer->id, $report->recorded_by);
        $this->assertSame(232.0, $report->totalPerDay());

        // RW 01: 232 kg/day; RW 02: 100 kg/day.
        // April has 30 days:
        // Total = 9,960 kg; diverted = 5,400 kg; TPS = 4,500 kg.
        $month = MonthlyWasteReduction::where('year', self::YEAR)
            ->where('month', self::MONTH)
            ->firstOrFail();

        $this->assertEquals(9960, (float) $month->total_waste_generated);
        $this->assertEquals(5400, (float) $month->waste_diverted);
        $this->assertEquals(4500, (float) $month->waste_to_tps);
        $this->assertEquals(54.22, (float) $month->reduction_percentage);
        $this->assertSame($officer->id, $month->recorded_by);
    }

    public function test_blank_days_means_the_whole_month(): void
    {
        $this->actingAs($this->userWithRole('officer'))->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [
                $this->rw(1) => $this->row(['days_counted' => '']),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(30, RwWasteReport::firstOrFail()->days_counted); // April 2026.
    }

    public function test_saving_again_updates_instead_of_duplicating(): void
    {
        $officer = $this->userWithRole('officer');

        $send = fn (array $row) => $this->actingAs($officer)->post(
            route('waste-weighings.store'),
            [
                'year' => self::YEAR,
                'month' => self::MONTH,
                'rows' => [$this->rw(1) => $row],
            ]
        )->assertSessionHasNoErrors();

        $send($this->row(['organic_kg_per_day' => 100]));
        $send($this->row(['organic_kg_per_day' => 10]));

        $this->assertSame(1, RwWasteReport::count());
        $this->assertSame(1, MonthlyWasteReduction::count());
        $this->assertEquals(10, (float) RwWasteReport::firstOrFail()->organic_kg_per_day);

        $this->assertEquals(
            (10 + 50 + 2 + 80) * 30,
            (float) MonthlyWasteReduction::firstOrFail()->total_waste_generated
        );
    }

    public function test_clearing_a_row_removes_it_and_updates_the_monthly_figures(): void
    {
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [
                $this->rw(1) => $this->row(),
                $this->rw(2) => $this->row(),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, RwWasteReport::count());

        $this->actingAs($officer)->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [
                $this->rw(1) => $this->row(),
                $this->rw(2) => $this->row([
                    'organic_kg_per_day' => 0,
                    'inorganic_kg_per_day' => 0,
                    'b3_kg_per_day' => 0,
                    'residue_kg_per_day' => 0,
                    'carts' => 0,
                    'collectors' => 0,
                    'pickup_frequency' => 0,
                ]),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, RwWasteReport::count());

        $this->assertEquals(
            232 * 30,
            (float) MonthlyWasteReduction::firstOrFail()->total_waste_generated
        );
    }

    public function test_an_all_empty_sheet_for_a_new_month_creates_no_record(): void
    {
        $this->actingAs($this->userWithRole('officer'))->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [
                $this->rw(1) => [
                    'organic_kg_per_day' => '',
                    'notes' => '',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, RwWasteReport::count());
        $this->assertSame(0, MonthlyWasteReduction::count());
    }

    public function test_a_remark_alone_is_enough_to_keep_a_row(): void
    {
        $this->actingAs($this->userWithRole('officer'))->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [
                $this->rw(1) => [
                    'organic_kg_per_day' => '',
                    'notes' => 'Cart broken',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Cart broken', RwWasteReport::firstOrFail()->notes);
    }

    public function test_the_target_of_the_previous_month_is_carried_forward(): void
    {
        $officer = $this->userWithRole('officer');
        $previousMonth = self::MONTH - 1;

        // Save the previous month's target, then create the selected month.
        $this->actingAs($officer)->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => $previousMonth,
            'rows' => [$this->rw(1) => $this->row()],
        ])->assertSessionHasNoErrors();

        MonthlyWasteReduction::where('year', self::YEAR)
            ->where('month', $previousMonth)
            ->firstOrFail()
            ->update(['target_percentage' => 40]);

        $this->actingAs($officer)->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [$this->rw(1) => $this->row()],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(
            40,
            (float) MonthlyWasteReduction::where('year', self::YEAR)
                ->where('month', self::MONTH)
                ->firstOrFail()
                ->target_percentage
        );
    }

    public function test_recalculating_keeps_a_manually_set_target(): void
    {
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => 1,
            'rows' => [$this->rw(1) => $this->row()],
        ])->assertSessionHasNoErrors();

        MonthlyWasteReduction::firstOrFail()
            ->update(['target_percentage' => 55, 'notes' => 'Keep me']);

        $this->actingAs($officer)->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => 1,
            'rows' => [
                $this->rw(1) => $this->row(['organic_kg_per_day' => 1]),
            ],
        ])->assertSessionHasNoErrors();

        $month = MonthlyWasteReduction::firstOrFail();

        $this->assertEquals(55, (float) $month->target_percentage);
        $this->assertSame('Keep me', $month->notes);
    }

    // ---- the rules live in config --------------------------------------------------------

    public function test_the_grouping_rules_come_from_config(): void
    {
        config([
            'monitoring.diverted_groups' => ['organik'],
            'monitoring.to_tps_groups' => ['residu', 'b3'],
        ]);

        $this->actingAs($this->userWithRole('officer'))->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [$this->rw(1) => $this->row()],
        ])->assertSessionHasNoErrors();

        $month = MonthlyWasteReduction::firstOrFail();

        $this->assertEquals(100 * 30, (float) $month->waste_diverted); // Organic only.
        $this->assertEquals((80 + 2) * 30, (float) $month->waste_to_tps); // Residue + B3.
    }

    public function test_b3_counts_in_the_total_only_by_default(): void
    {
        $this->actingAs($this->userWithRole('officer'))->post(route('waste-weighings.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'rows' => [
                $this->rw(1) => $this->row([
                    'organic_kg_per_day' => 0,
                    'inorganic_kg_per_day' => 0,
                    'residue_kg_per_day' => 0,
                    'b3_kg_per_day' => 10,
                ]),
            ],
        ])->assertSessionHasNoErrors();

        $month = MonthlyWasteReduction::firstOrFail();

        $this->assertEquals(300, (float) $month->total_waste_generated);
        $this->assertEquals(0, (float) $month->waste_diverted);
        $this->assertEquals(0, (float) $month->waste_to_tps);
        $this->assertEquals(0, (float) $month->reduction_percentage);
    }

    // ---- validation -------------------------------------------------------------------

    public function test_invalid_sheets_are_rejected_and_nothing_is_saved(): void
    {
        $officer = $this->actingAs($this->userWithRole('officer'));
        $next = today()->addMonth();
        $good = $this->row();

        $cases = [
            'future month' => [
                [
                    'year' => $next->year,
                    'month' => $next->month,
                    'rows' => [$this->rw(1) => $good],
                ],
                'month',
            ],
            'month 13' => [
                [
                    'year' => self::YEAR,
                    'month' => 13,
                    'rows' => [$this->rw(1) => $good],
                ],
                'month',
            ],
            'no rows' => [
                [
                    'year' => self::YEAR,
                    'month' => self::MONTH,
                    'rows' => [],
                ],
                'rows',
            ],
            'negative kg' => [
                [
                    'year' => self::YEAR,
                    'month' => self::MONTH,
                    'rows' => [$this->rw(1) => $this->row(['organic_kg_per_day' => -1])],
                ],
                'rows.' . $this->rw(1) . '.organic_kg_per_day',
            ],
            'text kg' => [
                [
                    'year' => self::YEAR,
                    'month' => self::MONTH,
                    'rows' => [$this->rw(1) => $this->row(['b3_kg_per_day' => 'abc'])],
                ],
                'rows.' . $this->rw(1) . '.b3_kg_per_day',
            ],
            'fractional carts' => [
                [
                    'year' => self::YEAR,
                    'month' => self::MONTH,
                    'rows' => [$this->rw(1) => $this->row(['carts' => 1.5])],
                ],
                'rows.' . $this->rw(1) . '.carts',
            ],
            'unknown RW' => [
                [
                    'year' => self::YEAR,
                    'month' => self::MONTH,
                    'rows' => [9999 => $good],
                ],
                'rows',
            ],
            'too many days' => [
                [
                    'year' => self::YEAR,
                    'month' => self::MONTH,
                    'rows' => [$this->rw(1) => $this->row(['days_counted' => 31])],
                ],
                'rows.' . $this->rw(1) . '.days_counted',
            ],
        ];

        foreach ($cases as $name => [$data, $errorKey]) {
            $officer->post(route('waste-weighings.store'), $data);

            $errors = session('errors');

            $this->assertTrue(
                $errors !== null && $errors->has($errorKey),
                "Expected an error on '{$errorKey}' for case: {$name}"
            );
        }

        $this->assertSame(0, RwWasteReport::count());
        $this->assertSame(0, MonthlyWasteReduction::count());
    }

    // ---- deposits as a cross-check ----------------------------------------------------

    public function test_deposits_per_month_ignore_voided_ones(): void
    {
        $plastic = $this->category('PLS', 2000);

        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])
            ->create(['deposit_date' => '2026-02-10']);

        WasteDeposit::factory()->withLines([[$plastic, 2.5, 2000]])
            ->create(['deposit_date' => '2026-02-28']);

        WasteDeposit::factory()->withLines([[$plastic, 99, 2000]])
            ->create(['deposit_date' => '2026-02-11'])
            ->delete(); // Voided.

        WasteDeposit::factory()->withLines([[$plastic, 7, 2000]])
            ->create(['deposit_date' => '2026-03-01']);

        $byMonth = app(MonitoringService::class)->depositedKgByMonth(2026);

        $this->assertEquals(12.5, $byMonth[2]);
        $this->assertEquals(7.0, $byMonth[3]);
        $this->assertFalse($byMonth->has(1));
    }
}