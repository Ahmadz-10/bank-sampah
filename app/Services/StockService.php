<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\WasteCategory;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock of a category = weight deposited - weight sold.
 * Voided (soft-deleted) deposits and sales are ignored.
 */
class StockService
{
    /**
     * Stock per category id. Categories with no movement at all are absent (= 0).
     *
     * @param  array<int>|null  $categoryIds  limit to these categories (null = all)
     * @return Collection<int, float>
     */
    public function levels(?array $categoryIds = null): Collection
    {
        $in = $this->sumByCategory('waste_deposit_details', 'waste_deposits', 'waste_deposit_id', $categoryIds);
        $out = $this->sumByCategory('waste_sale_details', 'waste_sales', 'waste_sale_id', $categoryIds);

        return $in->keys()->merge($out->keys())->unique()->mapWithKeys(
            fn ($id) => [(int) $id => round((float) ($in[$id] ?? 0) - (float) ($out[$id] ?? 0), 3)]
        );
    }

    public function level(int $categoryId): float
    {
        return (float) $this->levels([$categoryId])->get($categoryId, 0.0);
    }

    /**
     * Serialise stock-changing work on these categories (row locks on MariaDB/MySQL).
     * Call inside a DB transaction, before changing any lines.
     */
    public function lock(array $categoryIds): void
    {
        WasteCategory::query()->whereIn('id', $categoryIds)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /**
     * Throw if any of these categories now has negative stock. Call at the end of a
     * transaction, AFTER the change was applied, so a thrown exception rolls it back.
     *
     * @throws InsufficientStockException
     */
    public function assertNotNegative(array $categoryIds): void
    {
        if (! config('transactions.block_oversell', true)) {
            return;
        }

        $negative = $this->levels($categoryIds)->filter(fn ($level) => $level < 0);

        if ($negative->isEmpty()) {
            return;
        }

        $names = WasteCategory::query()->whereIn('id', $negative->keys())->pluck('name', 'id');

        $items = $negative->map(fn ($level, $id) => ($names[$id] ?? '#'.$id).' ('.__('short by :qty', ['qty' => Format::weight(abs($level))]).')')
            ->implode(', ');

        throw new InsufficientStockException(__('Not enough stock: :items.', ['items' => $items]));
    }

    /** @return Collection<int|string, mixed> category id => total weight */
    private function sumByCategory(string $details, string $headers, string $foreignKey, ?array $categoryIds): Collection
    {
        return DB::table("{$details} as d")
            ->join("{$headers} as h", 'h.id', '=', "d.{$foreignKey}")
            ->whereNull('h.deleted_at')
            ->when($categoryIds !== null, fn ($query) => $query->whereIn('d.waste_category_id', $categoryIds))
            ->groupBy('d.waste_category_id')
            ->selectRaw('d.waste_category_id as category_id, SUM(d.weight) as total')
            ->pluck('total', 'category_id');
    }
}
