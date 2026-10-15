<?php

namespace Tests\Feature\Transactions;

use App\Models\Customer;
use App\Models\WasteDeposit;
use App\Models\WastePrice;
use App\Models\WasteSale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\Concerns\CreatesWasteData;
use Tests\TestCase;

class WasteDepositTest extends TestCase
{
    use CreatesUsersWithRoles;
    use CreatesWasteData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function payload(Customer $customer, array $lines, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'deposit_date' => today()->toDateString(),
            'lines' => $lines,
        ], $overrides);
    }

    // ---- access -----------------------------------------------------------------------

    public function test_viewer_cannot_use_deposits(): void
    {
        $viewer = $this->userWithRole('viewer');
        $deposit = WasteDeposit::factory()->create();

        $this->actingAs($viewer)->get(route('waste-deposits.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('waste-deposits.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('waste-deposits.show', $deposit))->assertForbidden();
        $this->actingAs($viewer)->post(route('waste-deposits.store'), [])->assertForbidden();
    }

    public function test_officer_and_admin_can_open_the_pages(): void
    {
        $this->category('PLS', 2000);
        $deposit = WasteDeposit::factory()->create();

        foreach (['officer', 'admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('waste-deposits.index'))->assertOk()->assertViewHas('deposits');
            $this->actingAs($user)->get(route('waste-deposits.create'))->assertOk();
            $this->actingAs($user)->get(route('waste-deposits.show', $deposit))->assertOk();
        }
    }

    // ---- creating ---------------------------------------------------------------------

    public function test_a_deposit_with_several_lines_gets_correct_prices_and_totals(): void
    {
        $plastic = $this->category('PLS', 2000);
        $paper = $this->category('KRT', 1000);
        $customer = Customer::factory()->create();
        $officer = $this->userWithRole('officer');

        $response = $this->actingAs($officer)->post(route('waste-deposits.store'), $this->payload($customer, [
            ['waste_category_id' => $plastic->id, 'weight' => 2.5],
            ['waste_category_id' => $paper->id, 'weight' => 4],
        ]));

        $deposit = WasteDeposit::firstOrFail();
        $response->assertSessionHasNoErrors()->assertRedirect(route('waste-deposits.show', $deposit));

        $this->assertMatchesRegularExpression('/^STR-\d{4}$/', $deposit->deposit_number);
        $this->assertSame($officer->id, $deposit->officer_id);
        $this->assertSame($customer->id, $deposit->customer_id);
        $this->assertEquals(6.5, (float) $deposit->total_weight);
        $this->assertEquals(9000, (float) $deposit->total_amount);   // 2.5 x 2000 + 4 x 1000

        $lines = $deposit->details->keyBy('waste_category_id');
        $this->assertEquals(2000, (float) $lines[$plastic->id]->price_per_unit);
        $this->assertEquals(5000, (float) $lines[$plastic->id]->subtotal);
        $this->assertEquals(4000, (float) $lines[$paper->id]->subtotal);
    }

    public function test_the_price_comes_from_the_price_list_on_the_deposit_date(): void
    {
        $plastic = $this->category('PLS', 1000);                       // 1000 since 30 days ago
        WastePrice::factory()->create([
            'waste_category_id' => $plastic->id, 'buy_price' => 2000, 'effective_date' => today()->subDays(3)->toDateString(),
        ]);
        $customer = Customer::factory()->create();
        $officer = $this->userWithRole('officer');
        $line = [['waste_category_id' => $plastic->id, 'weight' => 2]];

        $this->actingAs($officer)->post(route('waste-deposits.store'), $this->payload($customer, $line, ['deposit_date' => today()->subDays(5)->toDateString()]));
        $this->actingAs($officer)->post(route('waste-deposits.store'), $this->payload($customer, $line));

        [$older, $newer] = WasteDeposit::orderBy('id')->get()->all();
        $this->assertEquals(2000, (float) $older->total_amount);       // 2 x 1000 (old price)
        $this->assertEquals(4000, (float) $newer->total_amount);       // 2 x 2000 (new price)
    }

    public function test_a_later_price_change_never_alters_a_saved_deposit(): void
    {
        $plastic = $this->category('PLS', 2000);
        $customer = Customer::factory()->create();
        $this->actingAs($this->userWithRole('officer'))->post(route('waste-deposits.store'), $this->payload($customer, [
            ['waste_category_id' => $plastic->id, 'weight' => 3],
        ]));
        $saved = WasteDeposit::orderByDesc('id')->firstOrFail();

        WastePrice::factory()->create(['waste_category_id' => $plastic->id, 'buy_price' => 9999, 'effective_date' => today()->toDateString()]);

        $this->assertEquals(6000, (float) $saved->fresh()->total_amount);
        $this->assertEquals(2000, (float) $saved->details()->first()->price_per_unit);
    }

    public function test_totals_prices_and_officer_sent_by_the_browser_are_ignored(): void
    {
        $plastic = $this->category('PLS', 2000);
        $customer = Customer::factory()->create();
        $officer = $this->userWithRole('officer');

        $this->actingAs($officer)->post(route('waste-deposits.store'), $this->payload($customer, [
            ['waste_category_id' => $plastic->id, 'weight' => 1, 'price_per_unit' => 1, 'subtotal' => 1],
        ], ['total_amount' => 1, 'total_weight' => 999, 'officer_id' => 999, 'deposit_number' => 'HACK-1']));

        $deposit = WasteDeposit::firstOrFail();
        $this->assertEquals(2000, (float) $deposit->total_amount);
        $this->assertEquals(1, (float) $deposit->total_weight);
        $this->assertSame($officer->id, $deposit->officer_id);
        $this->assertNotSame('HACK-1', $deposit->deposit_number);
    }

    public function test_invalid_deposits_are_rejected_and_nothing_is_saved(): void
    {
        $plastic = $this->category('PLS', 2000);
        $paper = $this->category('KRT', 1000);
        $retired = $this->category('OLD', 500, null, ['is_active' => false]);
        $customer = Customer::factory()->create();
        $inactive = Customer::factory()->create(['is_active' => false]);
        $deleted = Customer::factory()->create();
        $deleted->delete();
        $officer = $this->actingAs($this->userWithRole('officer'));
        $ok = [['waste_category_id' => $plastic->id, 'weight' => 1]];

        $cases = [
            'inactive customer' => [$this->payload($inactive, $ok), 'customer_id'],
            'deleted customer' => [$this->payload($deleted, $ok), 'customer_id'],
            'unknown customer' => [$this->payload($customer, $ok, ['customer_id' => 9999]), 'customer_id'],
            'no lines' => [$this->payload($customer, []), 'lines'],
            'unknown category' => [$this->payload($customer, [['waste_category_id' => 9999, 'weight' => 1]]), 'lines.0.waste_category_id'],
            'inactive category' => [$this->payload($customer, [['waste_category_id' => $retired->id, 'weight' => 1]]), 'lines.0.waste_category_id'],
            'duplicate category' => [$this->payload($customer, [
                ['waste_category_id' => $plastic->id, 'weight' => 1], ['waste_category_id' => $plastic->id, 'weight' => 2],
            ]), 'lines.0.waste_category_id'],
            'zero weight' => [$this->payload($customer, [['waste_category_id' => $plastic->id, 'weight' => 0]]), 'lines.0.weight'],
            'negative weight' => [$this->payload($customer, [['waste_category_id' => $plastic->id, 'weight' => -2]]), 'lines.0.weight'],
            'text weight' => [$this->payload($customer, [['waste_category_id' => $plastic->id, 'weight' => 'abc']]), 'lines.0.weight'],
            'future date' => [$this->payload($customer, $ok, ['deposit_date' => today()->addDay()->toDateString()]), 'deposit_date'],
            'no price on that date' => [$this->payload($customer, $ok, ['deposit_date' => today()->subDays(40)->toDateString()]), 'lines.0.waste_category_id'],
        ];

        foreach ($cases as $name => [$data, $errorKey]) {
            $officer->post(route('waste-deposits.store'), $data);

            $errors = session('errors');
            $this->assertTrue(
                $errors !== null && $errors->has($errorKey),
                "Expected a validation error on '{$errorKey}' for case: {$name}"
            );
        }

        $this->assertDatabaseCount('waste_deposits', 0);
        $this->assertDatabaseCount('waste_deposit_details', 0);
    }

    public function test_nothing_is_saved_when_one_line_cannot_be_priced(): void
    {
        $priced = $this->category('PLS', 2000);
        $unpriced = \App\Models\WasteCategory::factory()->create();   // has no price rows at all
        $customer = Customer::factory()->create();

        try {
            app(\App\Services\TransactionService::class)->createDeposit([
                'customer_id' => $customer->id,
                'deposit_date' => today()->toDateString(),
                'lines' => [
                    ['waste_category_id' => $priced->id, 'weight' => 1],
                    ['waste_category_id' => $unpriced->id, 'weight' => 1],
                ],
            ], $this->userWithRole('officer'));
            $this->fail('A DomainException was expected.');
        } catch (\DomainException) {
            // expected
        }

        $this->assertDatabaseCount('waste_deposits', 0);       // header rolled back with the failed line
        $this->assertDatabaseCount('waste_deposit_details', 0);
    }

    // ---- show / index -----------------------------------------------------------------

    public function test_the_detail_page_shows_the_lines(): void
    {
        $plastic = $this->category('PLS', 2000);
        $deposit = WasteDeposit::factory()->withLines([[$plastic, 2.5, 2000]])->create();

        $this->actingAs($this->userWithRole('officer'))
            ->get(route('waste-deposits.show', $deposit))
            ->assertOk()
            ->assertSee($deposit->deposit_number)
            ->assertSee($deposit->customer->name)
            ->assertSee('Pls waste');
    }

    public function test_the_list_filters_and_totals_match(): void
    {
        $plastic = $this->category('PLS', 1000);
        $ani = Customer::factory()->create(['name' => 'Ani Wijaya']);
        $budi = Customer::factory()->create(['name' => 'Budi Santoso']);
        WasteDeposit::factory()->withLines([[$plastic, 2, 1000]])->create(['customer_id' => $ani->id, 'deposit_date' => today()->toDateString()]);
        WasteDeposit::factory()->withLines([[$plastic, 1, 1000]])->create(['customer_id' => $budi->id, 'deposit_date' => today()->subDays(20)->toDateString()]);
        $user = $this->userWithRole('officer');

        $this->actingAs($user)->get(route('waste-deposits.index'))
            ->assertViewHas('totals', fn ($t) => (int) $t->cnt === 2 && (float) $t->amount === 3000.0);

        $this->actingAs($user)->get(route('waste-deposits.index', ['q' => 'Budi']))
            ->assertOk()->assertSee('Budi Santoso')->assertDontSee('Ani Wijaya')
            ->assertViewHas('totals', fn ($t) => (int) $t->cnt === 1 && (float) $t->weight === 1.0);

        $this->actingAs($user)->get(route('waste-deposits.index', ['from' => today()->subDays(5)->toDateString()]))
            ->assertOk()->assertSee('Ani Wijaya')->assertDontSee('Budi Santoso');

        $this->actingAs($user)->get(route('waste-deposits.index', ['to' => today()->subDays(5)->toDateString()]))
            ->assertOk()->assertSee('Budi Santoso')->assertDontSee('Ani Wijaya');
    }

    // ---- voiding ----------------------------------------------------------------------

    public function test_only_admins_can_void_and_the_deposit_is_soft_deleted(): void
    {
        $plastic = $this->category('PLS', 2000);
        $deposit = WasteDeposit::factory()->withLines([[$plastic, 5, 2000]])->create();

        $this->actingAs($this->userWithRole('officer'))->delete(route('waste-deposits.destroy', $deposit))->assertForbidden();
        $this->assertNotSoftDeleted($deposit);

        $this->actingAs($this->userWithRole('admin'))->delete(route('waste-deposits.destroy', $deposit))
            ->assertRedirect(route('waste-deposits.index'));

        $this->assertSoftDeleted($deposit);
        $this->assertEquals(0.0, app(StockService::class)->level($plastic->id));   // its weight left the stock
    }

    public function test_voided_deposits_are_hidden_but_admins_can_still_find_them(): void
    {
        $plastic = $this->category('PLS', 2000);
        $kept = WasteDeposit::factory()->withLines([[$plastic, 1, 2000]])->create();
        $voided = WasteDeposit::factory()->withLines([[$plastic, 1, 2000]])->create();
        $voided->delete();

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('waste-deposits.index'))->assertSee($kept->deposit_number)->assertDontSee($voided->deposit_number);
        $this->actingAs($admin)->get(route('waste-deposits.index', ['status' => 'voided']))->assertSee($voided->deposit_number)->assertDontSee($kept->deposit_number);
        $this->actingAs($admin)->get(route('waste-deposits.show', $voided))->assertOk()->assertSee(__('Voided'));

        // An officer cannot use the voided filter
        $this->actingAs($this->userWithRole('officer'))->get(route('waste-deposits.index', ['status' => 'voided']))
            ->assertSee($kept->deposit_number)->assertDontSee($voided->deposit_number);
    }

    public function test_a_voided_deposit_cannot_be_voided_again(): void
    {
        $deposit = WasteDeposit::factory()->create();
        $deposit->delete();

        $this->actingAs($this->userWithRole('admin'))->delete(route('waste-deposits.destroy', $deposit))->assertNotFound();
    }

    public function test_a_deposit_cannot_be_voided_when_its_weight_was_already_sold(): void
    {
        $plastic = $this->category('PLS', 2000);
        $deposit = WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        WasteSale::factory()->withLines([[$plastic, 8, 3000]])->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('waste-deposits.destroy', $deposit))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($deposit);
        $this->assertEquals(2.0, app(StockService::class)->level($plastic->id));
    }

    public function test_a_deposit_can_be_voided_when_enough_stock_remains(): void
    {
        $plastic = $this->category('PLS', 2000);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        $extra = WasteDeposit::factory()->withLines([[$plastic, 5, 2000]])->create();
        WasteSale::factory()->withLines([[$plastic, 8, 3000]])->create();     // stock 7

        $this->actingAs($this->userWithRole('admin'))->delete(route('waste-deposits.destroy', $extra))
            ->assertSessionHasNoErrors()->assertRedirect(route('waste-deposits.index'));

        $this->assertSoftDeleted($extra);
        $this->assertEquals(2.0, app(StockService::class)->level($plastic->id));
    }

    // ---- customer detail page ---------------------------------------------------------

    public function test_the_customer_page_shows_deposit_history_and_totals_without_voided_ones(): void
    {
        $plastic = $this->category('PLS', 1000);
        $customer = Customer::factory()->create();
        $a = WasteDeposit::factory()->withLines([[$plastic, 2, 1000]])->create(['customer_id' => $customer->id]);
        $b = WasteDeposit::factory()->withLines([[$plastic, 3, 1000]])->create(['customer_id' => $customer->id]);
        $b->delete();
        WasteDeposit::factory()->withLines([[$plastic, 9, 1000]])->create();   // someone else's

        $this->actingAs($this->userWithRole('officer'))
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($a->deposit_number)
            ->assertDontSee($b->deposit_number)
            ->assertViewHas('totals', fn ($t) => (int) $t->cnt === 1 && (float) $t->amount === 2000.0);
    }

    public function test_the_customer_page_is_closed_to_viewers(): void
    {
        $this->actingAs($this->userWithRole('viewer'))
            ->get(route('customers.show', Customer::factory()->create()))
            ->assertForbidden();
    }
}
