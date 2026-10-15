<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The kelurahan-wide monthly figures. Weights and the percentage are a ROLL-UP calculated
 * by MonitoringService from the RW weighings; only target_percentage and notes are typed.
 */
class MonthlyWasteReduction extends Model
{
    use HasFactory;

    protected $fillable = ['target_percentage', 'notes'];

    protected function casts(): array
    {
        return [
            'total_waste_generated' => 'decimal:2',
            'waste_diverted' => 'decimal:2',
            'waste_to_tps' => 'decimal:2',
            'reduction_percentage' => 'decimal:2',
            'target_percentage' => 'decimal:2',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function metTarget(): bool
    {
        return (float) $this->reduction_percentage >= (float) $this->target_percentage;
    }
}
