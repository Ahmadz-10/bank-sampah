<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use HasFactory, HasGeneratedCode, SoftDeletes;

    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_SORTING = 'sorting';
    public const STATUS_NOT_SORTING = 'not_sorting';

    // household_code and sorting_status are deliberately not fillable:
    // the code is generated, and the status is derived from validated surveys (Phase 3).
    protected $fillable = ['head_name', 'address', 'rt', 'rw', 'phone', 'member_count'];

    protected $attributes = [
        'sorting_status' => self::STATUS_UNKNOWN,
        'member_count' => 1,
    ];

    public static function codePrefix(): string
    {
        return 'RMH-';
    }

    public static function codeColumn(): string
    {
        return 'household_code';
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->sorting_status) {
            self::STATUS_SORTING => __('Sorting'),
            self::STATUS_NOT_SORTING => __('Not sorting'),
            default => __('Not surveyed'),
        };
    }

    /** Bootstrap colour name for the status badge. */
    public function statusBadge(): string
    {
        return match ($this->sorting_status) {
            self::STATUS_SORTING => 'success',
            self::STATUS_NOT_SORTING => 'warning',
            default => 'secondary',
        };
    }
}
