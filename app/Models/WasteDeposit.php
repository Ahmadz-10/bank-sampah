<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer hands in waste (setoran). Header only: the items are WasteDepositDetail rows.
 * Records are never edited: a mistake is corrected by voiding (soft delete) and re-entering.
 */
class WasteDeposit extends Model
{
    use HasFactory, HasGeneratedCode, SoftDeletes;

    // Totals and officer are set by the system, never by the request.
    protected $fillable = ['customer_id', 'deposit_date', 'notes'];

    protected $attributes = [
        'total_weight' => 0,
        'total_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'deposit_date' => 'date',
            'total_weight' => 'decimal:3',
            'total_amount' => 'decimal:2',
        ];
    }

    public static function codePrefix(): string
    {
        return 'STR-';
    }

    public static function codeColumn(): string
    {
        return 'deposit_number';
    }

    /** Includes soft-deleted customers so old deposits stay readable. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(WasteDepositDetail::class);
    }

    /** The ONE place where header totals are calculated from the lines. */
    public function recalculateTotals(): void
    {
        $this->forceFill([
            'total_weight' => round((float) $this->details()->sum('weight'), 3),
            'total_amount' => round((float) $this->details()->sum('subtotal'), 2),
        ])->save();
    }
}
