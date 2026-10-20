<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One RW's weighing for one month (a row of the "Laporan timbangan sampah").
 * Totals are always calculated here, never typed.
 */
class RwWasteReport extends Model
{
    use HasFactory;

    /** waste group => column holding its kg per day */
    public const GROUP_COLUMNS = [
        'organik' => 'organic_kg_per_day',
        'anorganik' => 'inorganic_kg_per_day',
        'b3' => 'b3_kg_per_day',
        'residu' => 'residue_kg_per_day',
    ];

    protected $fillable = [
        'rw_id', 'year', 'month',
        'organic_kg_per_day', 'inorganic_kg_per_day', 'b3_kg_per_day', 'residue_kg_per_day',
        'carts', 'collectors', 'pickup_frequency', 'days_counted', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'organic_kg_per_day' => 'decimal:2',
            'inorganic_kg_per_day' => 'decimal:2',
            'b3_kg_per_day' => 'decimal:2',
            'residue_kg_per_day' => 'decimal:2',
        ];
    }

    public function rw(): BelongsTo
    {
        return $this->belongsTo(Rw::class);
    }

    /** kg per day for one group, or for several groups added together. */
    public function groupsPerDay(array|string $groups): float
    {
        $sum = 0.0;

        foreach ((array) $groups as $group) {
            $sum += (float) $this->{self::GROUP_COLUMNS[$group]};
        }

        return $sum;
    }

    /** "Total kg/hari" */
    public function totalPerDay(): float
    {
        return $this->groupsPerDay(array_keys(self::GROUP_COLUMNS));
    }

    /** kg over the days counted, for the given groups (default: all). */
    public function monthlyKg(array|string|null $groups = null): float
    {
        return round($this->groupsPerDay($groups ?? array_keys(self::GROUP_COLUMNS)) * $this->days_counted, 2);
    }

    /**
     * "Terangkut kg/bulan": estimated weight carried away = total kg/day x days counted.
     * Pickup frequency is information only, NOT a multiplier (the original workbook
     * multiplied by it, which inflated the figure about 30 times; to be confirmed).
     */
    public function transportedKg(): float
    {
        return $this->monthlyKg();
    }
}
