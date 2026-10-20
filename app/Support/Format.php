<?php

namespace App\Support;

/**
 * Display helpers for quantities and money (Indonesian style: 1.234,5).
 */
final class Format
{
    /** "Rp 1.500" (decimals only when the amount really has cents). */
    public static function money(float|int|string|null $value): string
    {
        $number = (float) $value;
        $decimals = abs($number - round($number)) > 0.004 ? 2 : 0;

        return 'Rp '.number_format($number, $decimals, ',', '.');
    }

    /** "12,5" / "1.000" (up to 3 decimals, trailing zeros removed). */
    public static function weight(float|int|string|null $value): string
    {
        $text = number_format((float) $value, 3, ',', '.');

        return rtrim(rtrim($text, '0'), ',') ?: '0';
    }
}
