<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A "nasabah" (bank sampah account holder).
 */
class Customer extends Model
{
    use HasFactory, HasGeneratedCode, SoftDeletes;

    protected $fillable = [
        'household_id', 'name', 'id_number', 'phone', 'address', 'joined_at', 'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function codePrefix(): string
    {
        return 'NSB-';
    }

    public static function codeColumn(): string
    {
        return 'customer_code';
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(WasteDeposit::class);
    }
}
