<?php

namespace App\Support\Payroll;

/**
 * Money helpers for payroll. Arithmetic happens in integer centavos; the decimal
 * helpers are only the conversion at the database and HTTP edges.
 */
final class Money
{
    /** Parse a peso amount such as "45000.00" or 250 into integer centavos. */
    public static function toCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        $text = trim((string) $value);
        $negative = str_starts_with($text, '-');
        $text = ltrim($text, '+-');
        $text = str_replace([',', ' '], '', $text);

        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    /** Render integer centavos as a two-decimal string for a decimal column or API. */
    public static function toDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);

        return ($negative ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Half up, away from zero on an exact half. */
    public static function roundHalfUp(float|int $value): int
    {
        return (int) round($value, 0, PHP_ROUND_HALF_UP);
    }

    public static function multiply(int $amountCents, float $multiplier): int
    {
        return self::roundHalfUp($amountCents * $multiplier);
    }
}
