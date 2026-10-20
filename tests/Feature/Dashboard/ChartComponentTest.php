<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('en');
    }

    public function test_a_doughnut_with_only_zeros_is_replaced_by_the_message(): void
    {
        $this->blade('<x-chart type="doughnut" title="T" :labels="$labels" :series="$series" empty-text="Nothing yet" />', [
            'labels' => ['A', 'B'],
            'series' => [['label' => 'H', 'color' => ['success', 'warning'], 'data' => [0, 0]]],
        ])->assertSee('Nothing yet')->assertDontSee('<canvas', false);
    }

    public function test_a_doughnut_and_a_horizontal_bar_carry_their_type_and_options(): void
    {
        $doughnut = $this->blade('<x-chart type="doughnut" title="T" :labels="$labels" :series="$series" />', [
            'labels' => ['A', 'B'],
            'series' => [['label' => 'H', 'color' => ['success', 'warning'], 'data' => [3, 1]]],
        ]);
        $doughnut->assertSee('<canvas role="img"', false)->assertSee('&quot;type&quot;:&quot;doughnut&quot;', false);

        $bar = $this->blade('<x-chart type="bar" :horizontal="true" title="T" :labels="$labels" :series="$series" unit=" kg" />', [
            'labels' => ['A'],
            'series' => [['label' => 'W', 'color' => 'primary', 'data' => [4.5]]],
        ]);
        $bar->assertSee('&quot;type&quot;:&quot;bar&quot;', false)->assertSee('&quot;horizontal&quot;:true', false);
    }

    public function test_a_bar_or_line_with_a_real_zero_is_still_drawn(): void
    {
        // 0 is a result (a month that reduced nothing); only null means "no data".
        $this->blade('<x-chart type="line" title="T" :labels="$labels" :series="$series" />', [
            'labels' => ['Jan'],
            'series' => [['label' => 'R', 'color' => 'success', 'data' => [0]]],
        ])->assertSee('<canvas', false);

        $this->blade('<x-chart type="bar" title="T" :labels="$labels" :series="$series" />', [
            'labels' => ['Jan'],
            'series' => [['label' => 'R', 'color' => 'success', 'data' => [null]]],
        ])->assertDontSee('<canvas', false);
    }

    public function test_the_line_chart_wrapper_still_renders_a_line(): void
    {
        $this->blade('<x-line-chart title="T" :labels="$labels" :series="$series" unit="%" />', [
            'labels' => ['Jan', 'Feb'],
            'series' => [['label' => 'R', 'color' => 'success', 'data' => [10, null]]],
        ])->assertSee('&quot;type&quot;:&quot;line&quot;', false)->assertSee('&quot;unit&quot;:&quot;%&quot;', false);
    }

    public function test_the_stat_card_is_a_link_only_when_given_an_href(): void
    {
        $this->blade('<x-stat-card label="Households" value="12" hint="Sorting: 3" href="/households" />')
            ->assertSee('Households')->assertSee('12')->assertSee('Sorting: 3')->assertSee('href="/households"', false);

        $this->blade('<x-stat-card label="Households" value="12" />')
            ->assertSee('12')->assertDontSee('<a ', false);
    }
}
