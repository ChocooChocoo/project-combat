<?php

namespace App\Support\DecisionSupport;

final class Metrics
{
    public static function rate(int|float $numerator, int|float $denominator): ?float
    {
        return $denominator == 0 ? null : round(100 * $numerator / $denominator, 2);
    }
}
