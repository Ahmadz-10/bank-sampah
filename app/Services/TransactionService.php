<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteDeposit;
use App\Models\WasteSale;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * All writes for deposits and sales go through here, inside one DB transaction each,
 * so a header never exists without its lines and stock can never silently go negative.
 *
 * Input is expected to be validated already (see WasteDepositRequest / WasteSaleRequest).
 * Prices and totals are ALWAYS decided here, never taken from the request.
 */
class TransactionService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array{customer_id:int, deposit_date:string, notes?:?string, lines:array<int, array{waste_category_id:int, weight:float|int|string}>}  $data
     */
    public function createDeposit(array $data, User $officer): WasteDeposit
    {
        return DB::transaction(function () use ($data, $officer) {
            $categories = WasteCategory::query()
                ->with('prices')
                ->whereIn('id', collect($data['lines'])->pluck('waste_category_id'))
                ->get()
                ->keyBy('id');

            $deposit = new WasteDeposit(Arr::only($data, ['customer_id', 'deposit_date', 'notes']));
            $deposit->officer_id = $officer->id;
            $deposit->save();

            foreach ($data['lines'] as $line) {
                $category = $categories[$line['waste_category_id']];
                $price = $category->priceOn($data['deposit_date']);

                if ($price === null) {
                    throw new DomainException("No price for category {$category->code} on {$data['deposit_date']}.");
                }

                $weight = round((float) $line['weight'], 3);
                $unitPrice = (float) $price->buy_price;

                $deposit->details()->create([
                    'waste_category_id' => $category->id,
                    'weight' => $weight,
                    'price_per_unit' => $unitPrice,
                    'subtotal' => round($weight * $unitPrice, 2),
                ]);
            }

            $deposit->recalculateTotals();

            return $deposit;
        });
    }

    /**
     * Void (soft delete) a deposit. Refused when the weight was already sold.
     *
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function voidDeposit(WasteDeposit $deposit): void
    {
        DB::transaction(function () use ($deposit) {
            $categoryIds = $deposit->details()->pluck('waste_category_id')->unique()->values()->all();

            $this->stock->lock($categoryIds);
            $deposit->delete();
            $this->stock->assertNotNegative($categoryIds);
        });
    }

    /**
     * @param  array{buyer_name:string, buyer_contact?:?string, sale_date:string, notes?:?string, lines:array<int, array{waste_category_id:int, weight:float|int|string, price_per_unit:float|int|string}>}  $data
     *
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function createSale(array $data, User $officer): WasteSale
    {
        return DB::transaction(function () use ($data, $officer) {
            $categoryIds = collect($data['lines'])->pluck('waste_category_id')->unique()->values()->all();

            $this->stock->lock($categoryIds);

            $sale = new WasteSale(Arr::only($data, ['buyer_name', 'buyer_contact', 'sale_date', 'notes']));
            $sale->officer_id = $officer->id;
            $sale->save();

            foreach ($data['lines'] as $line) {
                $weight = round((float) $line['weight'], 3);
                $unitPrice = round((float) $line['price_per_unit'], 2);

                $sale->details()->create([
                    'waste_category_id' => (int) $line['waste_category_id'],
                    'weight' => $weight,
                    'price_per_unit' => $unitPrice,
                    'subtotal' => round($weight * $unitPrice, 2),
                ]);
            }

            $sale->recalculateTotals();

            // Checked after the lines exist, so the sale's own weight is already counted.
            $this->stock->assertNotNegative($categoryIds);

            return $sale;
        });
    }

    /** Voiding a sale returns its weight to stock, so no stock check is needed. */
    public function voidSale(WasteSale $sale): void
    {
        $sale->delete();
    }
}
