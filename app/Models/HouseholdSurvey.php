<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One survey visit to a household. Workflow: pending -> validated | rejected.
 * Only VALIDATED surveys affect the household's sorting status.
 */
class HouseholdSurvey extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const VALIDATED = 'validated';
    public const REJECTED = 'rejected';

    // Validation fields and photo_path are not fillable: they are set explicitly by the
    // controller so a request can never validate its own survey.
    protected $fillable = ['household_id', 'survey_date', 'sorting_status', 'notes'];

    protected $attributes = [
        'validation_status' => self::PENDING,
    ];

    protected function casts(): array
    {
        return [
            'survey_date' => 'date',
            'validated_at' => 'datetime',
        ];
    }

    /** Includes soft-deleted households so history stays readable. */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class)->withTrashed();
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyed_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isPending(): bool
    {
        return $this->validation_status === self::PENDING;
    }

    public function isValidated(): bool
    {
        return $this->validation_status === self::VALIDATED;
    }

    public function isRejected(): bool
    {
        return $this->validation_status === self::REJECTED;
    }

    /**
     * Validated surveys are locked (reopen them first). Otherwise the surveyor can change
     * their own survey, and anyone allowed to validate can change any.
     */
    public function canBeModifiedBy(User $user): bool
    {
        return ! $this->isValidated()
            && ($this->surveyed_by === $user->id || $user->can('validate-surveys'));
    }

    public function sortingLabel(): string
    {
        return $this->sorting_status === Household::STATUS_SORTING ? __('Sorting') : __('Not sorting');
    }

    public function sortingBadge(): string
    {
        return $this->sorting_status === Household::STATUS_SORTING ? 'success' : 'warning';
    }

    public function validationLabel(): string
    {
        return match ($this->validation_status) {
            self::VALIDATED => __('Validated'),
            self::REJECTED => __('Rejected'),
            default => __('Pending'),
        };
    }

    public function validationBadge(): string
    {
        return match ($this->validation_status) {
            self::VALIDATED => 'success',
            self::REJECTED => 'danger',
            default => 'secondary',
        };
    }

    /** Photos live on the private disk and are served through an authenticated route. */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('household-surveys.photo', $this) : null;
    }
}
