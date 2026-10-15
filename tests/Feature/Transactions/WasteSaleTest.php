<?php

namespace Tests\Feature\Transactions;

use App\Models\WasteDeposit;
use App\Models\WasteSale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\Concerns\CreatesWasteData;
use Tests\TestCase;

class WasteSaleTest extends TestCase
{
    use CreatesUsersWithRoles;
    use CreatesWasteData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function payload(array $lines, array $overrides = []): array
    {
        return array_merge([
            'buyer_name' => 'CV Daur Ulang Jaya',
            'buyer_contact' => '081200000000',
            'sale_date' => today()->toDateString(),
            'lines' => $lines,
        ], $overrides);
    }

    private function stockOf($category): float
    {
        return app(StockService::class)->level($category->id);
    }

    // ---- access -----------------------------------------------------------------------

    public function test_viewer_cannot_use_sales(): void
    {
        $viewer = $this->userWithRole('viewer');
        $sale = WasteSale::factory()->create();

        $this->actingAs($viewer)->get(route('waste-sales.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('waste-sales.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('waste-sales.show', $sale))->assertForbidden();
        $this->actingAs($viewer)->post(route('waste-sales.store'), [])->assertForbidden();
    }

    public function test_officer_and_admin_can_open_the_pages(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        $sale = WasteSale::factory()->create();

        foreach (['officer', 'admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('waste-sales.index'))->assertOk()->assertViewHas('sales');
            $this->actingAs($user)->get(route('waste-sales.create'))->assertOk();
            $this->actingAs($user)->get(route('waste-sales.show', $sale))->assertOk();
        }
    }

    // ---- creating ---------------------------------------------------------------------

    public function test_a_sale_takes_the_typed_price_and_reduces_stock(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        $paper = $this->category('KRT', 1000, 1300);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000], [$paper, 20, 1000]])->create();
        $officer = $this->userWithRole('officer');

        $response = $this->actingAs($officer)->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 4, 'price_per_unit' => 3000],   // not the reference 2500
            ['waste_category_id' => $paper->id, 'weight' => 5.5, 'price_per_unit' => 1200],
        ]));

        $sale = WasteSale::firstOrFail();
        $response->assertSessionHasNoErrors()->assertRedirect(route('waste-sales.show', $sale));

        $this->assertMatchesRegularExpression('/^PNJ-\d{4}$/', $sale->sale_number);
        $this->assertSame($officer->id, $sale->officer_id);
        $this->assertSame('CV Daur Ulang Jaya', $sale->buyer_name);
        $this->assertEquals(9.5, (float) $sale->total_weight);
        $this->assertEquals(18600, (float) $sale->total_amount);       // 4 x 3000 + 5.5 x 1200

        $this->assertEquals(6.0, $this->stockOf($plastic));
        $this->assertEquals(14.5, $this->stockOf($paper));
    }

    public function test_totals_and_officer_sent_by_the_browser_are_ignored(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 1, 'price_per_unit' => 3000, 'subtotal' => 1],
        ], ['total_amount' => 1, 'officer_id' => 999, 'sale_number' => 'HACK-1']));

        $sale = WasteSale::firstOrFail();
        $this->assertEquals(3000, (float) $sale->total_amount);
        $this->assertSame($officer->id, $sale->officer_id);
        $this->assertNotSame('HACK-1', $sale->sale_number);
    }

    // ---- stock rule -------------------------------------------------------------------

    public function test_selling_exactly_the_stock_is_allowed_but_not_a_gram_more(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 10.001, 'price_per_unit' => 3000],
        ]))->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('waste_sales', 0);

        $this->actingAs($officer)->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 10, 'price_per_unit' => 3000],
        ]))->assertSessionHasNoErrors();
        $this->assertEquals(0.0, $this->stockOf($plastic));
    }

    public function test_when_one_line_lacks_stock_nothing_at_all_is_saved(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        $paper = $this->category('KRT', 1000, 1300);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000], [$paper, 1, 1000]])->create();

        $this->actingAs($this->userWithRole('officer'))->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 5, 'price_per_unit' => 3000],
            ['waste_category_id' => $paper->id, 'weight' => 50, 'price_per_unit' => 1500],     // too much
        ]))->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('waste_sales', 0);
        $this->assertDatabaseCount('waste_sale_details', 0);
        $this->assertEquals(10.0, $this->stockOf($plastic));        // the valid line was rolled back too
    }

    public function test_the_error_names_the_category_and_the_shortfall(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();

        $this->actingAs($this->userWithRole('officer'))->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 12.5, 'price_per_unit' => 3000],
        ]));

        $message = session('errors')->first('lines');
        $this->assertStringContainsString('Pls waste', $message);
        $this->assertStringContainsString('2,5', $message);          // short by 2.5
    }

    public function test_the_stock_rule_can_be_switched_off_in_config(): void
    {
        config(['transactions.block_oversell' => false]);
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 1, 2000]])->create();

        $this->actingAs($this->userWithRole('officer'))->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 5, 'price_per_unit' => 3000],
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(-4.0, $this->stockOf($plastic));
    }

    public function test_a_retired_category_that_still_holds_stock_can_be_sold(): void
    {
        $old = $this->category('OLD', 500, 700, ['is_active' => false]);
        WasteDeposit::factory()->withLines([[$old, 3, 500]])->create();

        $this->actingAs($this->userWithRole('officer'))->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $old->id, 'weight' => 3, 'price_per_unit' => 800],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('waste_sales', 1);
    }

    public function test_voided_deposits_do_not_count_as_stock(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create()->delete();   // voided

        $this->actingAs($this->userWithRole('officer'))->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 1, 'price_per_unit' => 3000],
        ]))->assertSessionHasErrors('lines');
    }

    // ---- validation -------------------------------------------------------------------

    public function test_invalid_sales_are_rejected_and_nothing_is_saved(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 100, 2000]])->create();
        $officer = $this->actingAs($this->userWithRole('officer'));
        $line = ['waste_category_id' => $plastic->id, 'weight' => 1, 'price_per_unit' => 3000];

        $cases = [
            'no buyer' => [$this->payload([$line], ['buyer_name' => '']), 'buyer_name'],
            'future date' => [$this->payload([$line], ['sale_date' => today()->addDay()->toDateString()]), 'sale_date'],
            'no lines' => [$this->payload([]), 'lines'],
            'unknown category' => [$this->payload([array_merge($line, ['waste_category_id' => 9999])]), 'lines.0.waste_category_id'],
            'duplicate category' => [$this->payload([$line, $line]), 'lines.0.waste_category_id'],
            'zero weight' => [$this->payload([array_merge($line, ['weight' => 0])]), 'lines.0.weight'],
            'missing price' => [$this->payload([['waste_category_id' => $plastic->id, 'weight' => 1]]), 'lines.0.price_per_unit'],
            'negative price' => [$this->payload([array_merge($line, ['price_per_unit' => -1])]), 'lines.0.price_per_unit'],
        ];

        foreach ($cases as $name => [$data, $errorKey]) {
            $officer->post(route('waste-sales.store'), $data);

            $errors = session('errors');
            $this->assertTrue(
                $errors !== null && $errors->has($errorKey),
                "Expected a validation error on '{$errorKey}' for case: {$name}"
            );
        }

        $this->assertDatabaseCount('waste_sales', 0);
    }

    public function test_a_price_of_zero_is_allowed(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 5, 2000]])->create();

        $this->actingAs($this->userWithRole('officer'))->post(route('waste-sales.store'), $this->payload([
            ['waste_category_id' => $plastic->id, 'weight' => 1, 'price_per_unit' => 0],
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(0, (float) WasteSale::firstOrFail()->total_amount);
    }

    // ---- voiding ----------------------------------------------------------------------

    public function test_only_admins_can_void_and_the_weight_returns_to_stock(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        $sale = WasteSale::factory()->withLines([[$plastic, 4, 3000]])->create();
        $this->assertEquals(6.0, $this->stockOf($plastic));

        $this->actingAs($this->userWithRole('officer'))->delete(route('waste-sales.destroy', $sale))->assertForbidden();
        $this->assertNotSoftDeleted($sale);

        $this->actingAs($this->userWithRole('admin'))->delete(route('waste-sales.destroy', $sale))
            ->assertRedirect(route('waste-sales.index'));

        $this->assertSoftDeleted($sale);
        $this->assertEquals(10.0, $this->stockOf($plastic));
    }

    public function test_voided_sales_are_hidden_from_the_default_list_but_visible_to_admins_on_request(): void
    {
        $kept = WasteSale::factory()->create();
        $voided = WasteSale::factory()->create();
        $voided->delete();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('waste-sales.index'))->assertSee($kept->sale_number)->assertDontSee($voided->sale_number);
        $this->actingAs($admin)->get(route('waste-sales.index', ['status' => 'voided']))->assertSee($voided->sale_number)->assertDontSee($kept->sale_number);
        $this->actingAs($admin)->get(route('waste-sales.show', $voided))->assertOk()->assertSee(__('Voided'));
    }

    // ---- list + stock panel -----------------------------------------------------------

    public function test_the_list_shows_current_stock_and_filters(): void
    {
        $plastic = $this->category('PLS', 2000, 2500);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        WasteSale::factory()->withLines([[$plastic, 3, 3000]])->create(['buyer_name' => 'CV Alpha', 'sale_date' => today()->toDateString()]);
        WasteSale::factory()->withLines([[$plastic, 1, 3000]])->create(['buyer_name' => 'PT Beta', 'sale_date' => today()->subDays(20)->toDateString()]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('waste-sales.index'))
            ->assertOk()
            ->assertViewHas('stockRows', function ($rows) use ($plastic) {
                $row = $rows->first(fn ($r) => $r['category']->id === $plastic->id);

                return $row !== null && $row['level'] === 6.0;     // 10 - 3 - 1
            })
            ->assertViewHas('totals', fn ($t) => (int) $t->cnt === 2 && (float) $t->amount === 12000.0);

        $this->actingAs($admin)->get(route('waste-sales.index', ['q' => 'Alpha']))
            ->assertSee('CV Alpha')->assertDontSee('PT Beta');
        $this->actingAs($admin)->get(route('waste-sales.index', ['from' => today()->subDays(5)->toDateString()]))
            ->assertSee('CV Alpha')->assertDontSee('PT Beta');
    }
}
