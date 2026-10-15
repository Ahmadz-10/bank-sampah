<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a deposit. price_per_unit is a SNAPSHOT of the buy price on the deposit
 * date, so later price changes never alter old deposits.
 */
class WasteDepositDetail extends Model
{
    protected $fillable = ['waste_category_id', 'weight', 'price_per_unit', 'subtotal'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'price_per_unit' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(WasteDeposit::class, 'waste_deposit_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }
}
