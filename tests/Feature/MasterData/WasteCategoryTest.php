<?php

namespace Tests\Feature\MasterData;

use App\Models\WasteCategory;
use App\Models\WastePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class WasteCategoryTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'code' => 'pls',
            'name' => 'Plastik',
            'unit' => 'kg',
            'is_active' => 1,
            'buy_price' => 2000,
            'sell_price' => 2500,
            'effective_date' => today()->toDateString(),
        ], $overrides);
    }

    public function test_only_admin_and_super_admin_can_manage_categories(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('waste-categories.index'))->assertOk();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('waste-categories.index'))->assertOk();
        $this->actingAs($this->userWithRole('officer'))->get(route('waste-categories.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('viewer'))->get(route('waste-categories.index'))->assertForbidden();
    }

    public function test_creating_a_category_also_creates_its_first_price_and_uppercases_the_code(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('waste-categories.store'), $this->validData())
            ->assertRedirect(route('waste-categories.index'));

        $category = WasteCategory::firstOrFail();
        $this->assertSame('PLS', $category->code);

        $price = $category->prices()->firstOrFail();
        $this->assertEquals(2000, (float) $price->buy_price);
        $this->assertEquals(2500, (float) $price->sell_price);
        $this->assertSame($admin->id, $price->created_by);
    }

    public function test_code_must_be_unique(): void
    {
        WasteCategory::factory()->create(['code' => 'PLS']);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('waste-categories.store'), $this->validData(['code' => 'pls']))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('waste_categories', 1);
    }

    public function test_initial_price_is_required_when_creating(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('waste-categories.store'), $this->validData(['buy_price' => '']))
            ->assertSessionHasErrors('buy_price');

        $this->assertDatabaseCount('waste_categories', 0);
        $this->assertDatabaseCount('waste_prices', 0);
    }

    public function test_unit_must_be_one_of_the_allowed_values(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('waste-categories.store'), $this->validData(['unit' => 'tons']))
            ->assertSessionHasErrors('unit');
    }

    public function test_update_does_not_touch_prices_and_can_deactivate(): void
    {
        $category = WasteCategory::factory()->create(['code' => 'PLS']);
        WastePrice::factory()->create(['waste_category_id' => $category->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('waste-categories.update', $category), [
                'code' => 'PLS', 'name' => 'Plastik Baru', 'unit' => 'kg', 'is_active' => 0,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('waste-categories.index'));

        $category->refresh();
        $this->assertSame('Plastik Baru', $category->name);
        $this->assertFalse($category->is_active);
        $this->assertSame(1, $category->prices()->count());
    }

    public function test_categories_cannot_be_deleted(): void
    {
        $category = WasteCategory::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete('/waste-categories/'.$category->id)
            ->assertStatus(405);

        $this->assertDatabaseCount('waste_categories', 1);
    }
}
