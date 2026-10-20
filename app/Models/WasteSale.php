<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The bank sells collected waste to a buyer (penjualan). Like deposits: voided, not edited.
 */
class WasteSale extends Model
{
    use HasFactory, HasGeneratedCode, SoftDeletes;

    protected $fillable = ['buyer_name', 'buyer_contact', 'sale_date', 'notes'];

    protected $attributes = [
        'total_weight' => 0,
        'total_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'total_weight' => 'decimal:3',
            'total_amount' => 'decimal:2',
        ];
    }

    public static function codePrefix(): string
    {
        return 'PNJ-';
    }

    public static function codeColumn(): string
    {
        return 'sale_number';
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(WasteSaleDetail::class);
    }

    public function recalculateTotals(): void
    {
        $this->forceFill([
            'total_weight' => round((float) $this->details()->sum('weight'), 3),
            'total_amount' => round((float) $this->details()->sum('subtotal'), 2),
        ])->save();
    }
}
