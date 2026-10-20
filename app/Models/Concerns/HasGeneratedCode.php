<?php

namespace App\Models\Concerns;

/**
 * Auto-generates a human-readable unique code (e.g. RMH-0001) when a record is created.
 *
 * The using model must define codePrefix() and codeColumn(). Soft-deleted rows are
 * taken into account, so a code is never reused. The code column is intentionally
 * NOT mass-assignable: it is set only here.
 */
trait HasGeneratedCode
{
    abstract public static function codePrefix(): string;

    abstract public static function codeColumn(): string;

    protected static function bootHasGeneratedCode(): void
    {
        static::creating(function ($model) {
            $column = static::codeColumn();

            if (blank($model->{$column})) {
                $model->{$column} = static::nextCode();
            }
        });
    }

    public static function nextCode(): string
    {
        $next = (int) static::query()->withoutGlobalScopes()->max('id') + 1;

        do {
            $code = static::codePrefix().str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::query()->withoutGlobalScopes()->where(static::codeColumn(), $code)->exists());

        return $code;
    }
}
