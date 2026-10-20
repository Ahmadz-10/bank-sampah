<?php

namespace Tests\Feature\MasterData;

use App\Models\WasteCategory;
use App\Models\WastePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class WastePriceTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function priceOn(WasteCategory $category, string $date, int $buy): WastePrice
    {
        return WastePrice::factory()->create([
            'waste_category_id' => $category->id,
            'effective_date' => $date,
            'buy_price' => $buy,
        ]);
    }

    public function test_price_in_effect_depends_on_the_date(): void
    {
        $category = WasteCategory::factory()->create();
        $this->priceOn($category, today()->subDays(10)->toDateString(), 1000);
        $this->priceOn($category, today()->toDateString(), 1500);
        $this->priceOn($category, today()->addDays(10)->toDateString(), 2000);

        // Query path (relation not loaded)
        $this->assertEquals(1500, (float) $category->priceOn()->buy_price);
        $this->assertEquals(1000, (float) $category->priceOn(today()->subDays(5))->buy_price);
        $this->assertEquals(2000, (float) $category->priceOn(today()->addDays(10))->buy_price);
        $this->assertNull($category->priceOn(today()->subDays(30)));

        // Eager-loaded path must give the same answers
        $loaded = WasteCategory::with('prices')->find($category->id);
        $this->assertEquals(1500, (float) $loaded->priceOn()->buy_price);
        $this->assertEquals(1000, (float) $loaded->priceOn(today()->subDays(5))->buy_price);
        $this->assertNull($loaded->priceOn(today()->subDays(30)));
    }

    public function test_only_admin_can_manage_prices(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('waste-prices.index'))->assertOk();
        $this->actingAs($this->userWithRole('officer'))->get(route('waste-prices.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('viewer'))->get(route('waste-prices.index'))->assertForbidden();
    }

    public function test_admin_can_add_a_new_price_row(): void
    {
        $admin = $this->userWithRole('admin');
        $category = WasteCategory::factory()->create();

        $this->actingAs($admin)
            ->post(route('waste-prices.store'), [
                'waste_category_id' => $category->id,
                'buy_price' => 3000,
                'sell_price' => 3600,
                'effective_date' => today()->addDay()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('waste-prices.index', ['category' => $category->id]));

        $price = $category->prices()->firstOrFail();
        $this->assertEquals(3000, (float) $price->buy_price);
        $this->assertSame($admin->id, $price->created_by);
    }

    public function test_one_price_per_category_per_date(): void
    {
        $category = WasteCategory::factory()->create();
        $other = WasteCategory::factory()->create();
        $date = today()->toDateString();
        $this->priceOn($category, $date, 1000);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('waste-prices.store'), [
                'waste_category_id' => $category->id, 'buy_price' => 1200, 'effective_date' => $date,
            ])
            ->assertSessionHasErrors('effective_date');

        // The same date is fine for a different category
        $this->actingAs($admin)
            ->post(route('waste-prices.store'), [
                'waste_category_id' => $other->id, 'buy_price' => 1200, 'effective_date' => $date,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_price_can_be_edited_but_not_moved_to_another_category(): void
    {
        $category = WasteCategory::factory()->create();
        $other = WasteCategory::factory()->create();
        $price = $this->priceOn($category, today()->toDateString(), 1000);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('waste-prices.update', $price), [
                'waste_category_id' => $other->id,        // must be ignored
                'buy_price' => 1800,
                'effective_date' => today()->toDateString(), // same row, so not a duplicate of itself
            ])
            ->assertSessionHasNoErrors();

        $price->refresh();
        $this->assertEquals(1800, (float) $price->buy_price);
        $this->assertSame($category->id, $price->waste_category_id);
    }

    public function test_the_only_price_of_a_category_cannot_be_deleted(): void
    {
        $category = WasteCategory::factory()->create();
        $only = $this->priceOn($category, today()->toDateString(), 1000);

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('waste-prices.destroy', $only))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('waste_prices', 1);
    }

    public function test_a_price_can_be_deleted_when_the_category_has_others(): void
    {
        $category = WasteCategory::factory()->create();
        $old = $this->priceOn($category, today()->subDays(10)->toDateString(), 1000);
        $this->priceOn($category, today()->toDateString(), 1500);

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('waste-prices.destroy', $old))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('waste_prices', 1);
    }

    public function test_negative_prices_are_rejected(): void
    {
        $category = WasteCategory::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('waste-prices.store'), [
                'waste_category_id' => $category->id, 'buy_price' => -5, 'effective_date' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('buy_price');
    }

    public function test_the_index_page_renders_with_a_category_filter(): void
    {
        $category = WasteCategory::factory()->create();
        $this->priceOn($category, today()->toDateString(), 1000);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('waste-prices.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSee($category->code);
    }
}
