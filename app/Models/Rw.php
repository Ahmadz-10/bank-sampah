<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A neighbourhood unit (Rukun Warga). The kelurahan reports waste per RW. */
class Rw extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'chairman_name', 'household_count', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** "RW 01" */
    public function getNameAttribute(): string
    {
        return 'RW '.str_pad((string) $this->number, 2, '0', STR_PAD_LEFT);
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(RwWasteReport::class);
    }
}
