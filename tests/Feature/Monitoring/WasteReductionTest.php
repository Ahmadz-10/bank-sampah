<?php

namespace Tests\Feature\Monitoring;

use App\Models\MonthlyWasteReduction;
use App\Models\Rw;
use App\Models\RwWasteReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class WasteReductionTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function record(int $month, array $attributes = []): MonthlyWasteReduction
    {
        return MonthlyWasteReduction::factory()->create(array_merge(['year' => 2026, 'month' => $month], $attributes));
    }

    public function test_only_admins_can_see_the_summary(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('waste-reductions.index'))->assertOk();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('waste-reductions.index'))->assertOk();
        $this->actingAs($this->userWithRole('officer'))->get(route('waste-reductions.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('viewer'))->get(route('waste-reductions.index'))->assertForbidden();
    }

    public function test_the_index_shows_all_twelve_months_with_the_year_total(): void
    {
        $this->record(1, ['total_waste_generated' => 10000, 'waste_diverted' => 3000, 'waste_to_tps' => 6000]);
        $this->record(2, ['total_waste_generated' => 30000, 'waste_diverted' => 15000, 'waste_to_tps' => 12000]);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('waste-reductions.index', ['year' => 2026]))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 12 && $rows[0]['record'] !== null && $rows[5]['record'] === null)
            ->assertViewHas('totals', fn ($t) => $t['generated'] === 40000.0 && $t['diverted'] === 18000.0 && $t['percentage'] === 45.0);
    }

    public function test_the_year_filter_and_a_bad_year(): void
    {
        $this->record(1);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('waste-reductions.index', ['year' => 2026]))->assertViewHas('year', 2026);
        $this->actingAs($admin)->get(route('waste-reductions.index', ['year' => 1066]))->assertViewHas('year', (int) today()->format('Y'));
    }

    public function test_the_index_counts_reporting_rws_per_month(): void
    {
        $this->record(2);
        RwWasteReport::factory()->create(['year' => 2026, 'month' => 2, 'rw_id' => Rw::where('number', 1)->value('id')]);
        RwWasteReport::factory()->create(['year' => 2026, 'month' => 2, 'rw_id' => Rw::where('number', 2)->value('id')]);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('waste-reductions.index', ['year' => 2026]))
            ->assertViewHas('rows', fn ($rows) => $rows[1]['rw_reported'] === 2 && $rows[0]['rw_reported'] === 0);
    }

    public function test_the_target_can_be_set_but_the_calculated_figures_cannot(): void
    {
        $record = $this->record(3, ['reduction_percentage' => 30, 'total_waste_generated' => 10000, 'target_percentage' => 25]);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('waste-reductions.update', $record), [
                'target_percentage' => 40, 'notes' => 'Raised',
                'reduction_percentage' => 99, 'total_waste_generated' => 1, 'waste_diverted' => 1,   // must be ignored
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('waste-reductions.index', ['year' => 2026]));

        $record->refresh();
        $this->assertEquals(40, (float) $record->target_percentage);
        $this->assertSame('Raised', $record->notes);
        $this->assertEquals(30, (float) $record->reduction_percentage);
        $this->assertEquals(10000, (float) $record->total_waste_generated);
    }

    public function test_the_target_must_be_between_0_and_100(): void
    {
        $record = $this->record(3);
        $admin = $this->userWithRole('admin');

        foreach ([-1, 101, 'abc', ''] as $bad) {
            $this->actingAs($admin)->put(route('waste-reductions.update', $record), ['target_percentage' => $bad])
                ->assertSessionHasErrors('target_percentage');
        }

        $this->actingAs($admin)->put(route('waste-reductions.update', $record), ['target_percentage' => 0])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('waste-reductions.update', $record), ['target_percentage' => 100])->assertSessionHasNoErrors();
    }

    public function test_officers_cannot_change_targets(): void
    {
        $record = $this->record(3, ['target_percentage' => 25]);

        $this->actingAs($this->userWithRole('officer'))
            ->put(route('waste-reductions.update', $record), ['target_percentage' => 90])->assertForbidden();

        $this->assertEquals(25, (float) $record->fresh()->target_percentage);
    }

    public function test_records_cannot_be_created_or_deleted_by_hand(): void
    {
        $record = $this->record(3);
        $admin = $this->userWithRole('admin');

        $this->assertFalse(Route::has('waste-reductions.create'));
        $this->assertFalse(Route::has('waste-reductions.store'));
        $this->assertFalse(Route::has('waste-reductions.destroy'));

        $this->actingAs($admin)->post('/waste-reductions', ['year' => 2026, 'month' => 5])->assertStatus(405);
        $this->actingAs($admin)->delete('/waste-reductions/'.$record->id)->assertStatus(405);
        $this->assertModelExists($record);
    }

    public function test_the_index_charts_reduction_against_target_with_gaps_for_empty_months(): void
    {
        app()->setLocale('id');
        $this->record(1, ['reduction_percentage' => 30, 'target_percentage' => 25]);
        $this->record(5, ['reduction_percentage' => 41.256, 'target_percentage' => 40]);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('waste-reductions.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('data-chart', false)
            ->assertSee('<canvas', false)
            ->assertViewHas('chart', function ($chart) {
                [$reduction, $target] = $chart['series'];

                return count($chart['labels']) === 12
                    && $chart['labels'][4] === 'Mei'                       // Indonesian month name, not "May"
                    && $reduction['data'][0] === 30.0
                    && $reduction['data'][4] === 41.26                     // rounded to 2 decimals
                    && $reduction['data'][1] === null                      // no weighing = a gap, never 0
                    && $target['data'][0] === 25.0
                    && $target['data'][1] === null
                    && $target['dashed'] === true;
            });
    }

    public function test_the_chart_is_replaced_by_a_message_when_the_year_has_no_data(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('waste-reductions.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('No data to chart yet.')
            ->assertDontSee('<canvas', false);
    }

    public function test_the_line_chart_component_escapes_its_text_and_skips_empty_series(): void
    {
        $series = [['label' => '"><script>alert(1)</script>', 'color' => 'success', 'data' => [10, null, 20]]];

        $this->blade('<x-line-chart :title="$title" :labels="$labels" :series="$series" unit="%" />', [
            'title' => 'Title "quoted"',
            'labels' => ['Jan', 'Feb', 'Mar'],
            'series' => $series,
        ])
            ->assertSee('<canvas role="img"', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->blade('<x-line-chart title="x" :labels="[\'Jan\']" :series="[[\'label\' => \'A\', \'data\' => [null]]]" empty-text="Nothing yet" />')
            ->assertSee('Nothing yet')
            ->assertDontSee('<canvas', false);
    }

    public function test_the_target_met_flag(): void
    {
        $this->assertTrue($this->record(1, ['reduction_percentage' => 30, 'target_percentage' => 30])->metTarget());
        $this->assertFalse($this->record(2, ['reduction_percentage' => 29.99, 'target_percentage' => 30])->metTarget());
    }
}
