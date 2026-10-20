<?php

namespace Tests\Feature\Transactions;

use App\Exceptions\InsufficientStockException;
use App\Models\WasteDeposit;
use App\Models\WasteSale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWasteData;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use CreatesWasteData;
    use RefreshDatabase;

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    public function test_stock_is_deposits_minus_sales_per_category(): void
    {
        $plastic = $this->category('PLS', 2000);
        $paper = $this->category('KRT', 1000);
        $metal = $this->category('LGM', 4000);

        WasteDeposit::factory()->withLines([[$plastic, 10, 2000], [$paper, 4, 1000]])->create();
        WasteDeposit::factory()->withLines([[$plastic, 2.5, 2000]])->create();
        WasteSale::factory()->withLines([[$plastic, 6, 3000]])->create();

        $levels = $this->stock()->levels();
        $this->assertEquals(6.5, $levels[$plastic->id]);
        $this->assertEquals(4.0, $levels[$paper->id]);
        $this->assertFalse($levels->has($metal->id));                  // no movement at all
        $this->assertEquals(0.0, $this->stock()->level($metal->id));
    }

    public function test_voided_records_are_ignored(): void
    {
        $plastic = $this->category('PLS', 2000);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000]])->create();
        WasteDeposit::factory()->withLines([[$plastic, 99, 2000]])->create()->delete();
        WasteSale::factory()->withLines([[$plastic, 4, 3000]])->create();
        WasteSale::factory()->withLines([[$plastic, 50, 3000]])->create()->delete();

        $this->assertEquals(6.0, $this->stock()->level($plastic->id));
    }

    public function test_levels_can_be_limited_to_some_categories(): void
    {
        $plastic = $this->category('PLS', 2000);
        $paper = $this->category('KRT', 1000);
        WasteDeposit::factory()->withLines([[$plastic, 10, 2000], [$paper, 4, 1000]])->create();

        $only = $this->stock()->levels([$paper->id]);

        $this->assertSame([$paper->id], $only->keys()->all());
    }

    public function test_weights_with_three_decimals_do_not_drift(): void
    {
        $plastic = $this->category('PLS', 2000);
        WasteDeposit::factory()->withLines([[$plastic, 0.1, 2000]])->create();
        WasteDeposit::factory()->withLines([[$plastic, 0.2, 2000]])->create();
        WasteSale::factory()->withLines([[$plastic, 0.3, 3000]])->create();

        $this->assertSame(0.0, $this->stock()->level($plastic->id));    // 0.1 + 0.2 - 0.3 is exactly zero after rounding
    }

    public function test_assert_not_negative_throws_and_names_the_category(): void
    {
        $plastic = $this->category('PLS', 2000);
        WasteSale::factory()->withLines([[$plastic, 3, 3000]])->create();      // sold without any deposit

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Pls waste');

        $this->stock()->assertNotNegative([$plastic->id]);
    }

    public function test_assert_not_negative_passes_at_zero_and_when_disabled(): void
    {
        $plastic = $this->category('PLS', 2000);
        WasteDeposit::factory()->withLines([[$plastic, 3, 2000]])->create();
        WasteSale::factory()->withLines([[$plastic, 3, 3000]])->create();

        $this->stock()->assertNotNegative([$plastic->id]);                      // exactly zero is fine
        $this->addToAssertionCount(1);

        WasteSale::factory()->withLines([[$plastic, 1, 3000]])->create();       // now -1
        config(['transactions.block_oversell' => false]);
        $this->stock()->assertNotNegative([$plastic->id]);                      // switched off: no exception
        $this->addToAssertionCount(1);
    }
}
