<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

class WasteCategory extends Model
{
    use HasFactory;

    public const UNITS = ['kg' => 'kg', 'liter' => 'liter', 'pcs' => 'pcs'];

    /** The four groups of the kelurahan's weighbridge report. value => label key */
    public const GROUPS = ['organik' => 'Organic', 'anorganik' => 'Inorganic', 'b3' => 'B3 (hazardous)', 'residu' => 'Residue'];

    protected $fillable = ['code', 'name', 'unit', 'waste_group', 'description', 'is_active'];

    protected $attributes = [
        'unit' => 'kg',
        'waste_group' => 'anorganik',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(WastePrice::class);
    }

    public function depositDetails(): HasMany
    {
        return $this->hasMany(WasteDepositDetail::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(WasteSaleDetail::class);
    }

    /**
     * The price in effect on a given date (default: today), or null if none yet.
     * Uses the already-loaded `prices` relation when available (avoids N+1 on lists).
     * Phase 4 (deposits) calls this with the deposit date.
     */
    public function priceOn(CarbonInterface|string|null $date = null): ?WastePrice
    {
        $day = ($date === null ? today() : Carbon::parse($date))->toDateString();

        if ($this->relationLoaded('prices')) {
            return $this->prices
                ->filter(fn (WastePrice $price) => $price->effective_date->toDateString() <= $day)
                ->sortByDesc(fn (WastePrice $price) => $price->effective_date->toDateString().'#'.str_pad((string) $price->id, 10, '0', STR_PAD_LEFT))
                ->first();
        }

        return $this->prices()
            ->whereDate('effective_date', '<=', $day)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }
}
