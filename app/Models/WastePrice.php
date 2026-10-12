<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per price change. The price for a date is the latest row with
 * effective_date <= that date (see WasteCategory::priceOn()).
 */
class WastePrice extends Model
{
    use HasFactory;

    protected $fillable = ['waste_category_id', 'buy_price', 'sell_price', 'effective_date'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'buy_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
