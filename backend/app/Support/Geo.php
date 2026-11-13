<?php

namespace App\Support;

/** Distance between two map points (R-122). */
class Geo
{
    public static function distanceKm(float $lat, float $lng, float $toLat, float $toLng): float
    {
        $earthKm = 6371.0;
        $latDelta = deg2rad($toLat - $lat);
        $lngDelta = deg2rad($toLng - $lng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
