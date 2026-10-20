<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a sale. price_per_unit is the agreed selling price, typed per line. */
class WasteSaleDetail extends Model
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WasteSale::class, 'waste_sale_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }
}
