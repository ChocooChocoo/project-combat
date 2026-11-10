<?php

namespace App\Support;

/** The only place gyms may be: Cavite. Data from PSGC; zip per city or municipality. */
class CaviteLocations
{
    public const PROVINCE = 'Cavite';

    private static ?array $data = null;

    public static function all(): array
    {
        return self::$data ??= json_decode(
            file_get_contents(resource_path('data/cavite-locations.json')), true
        );
    }

    public static function find(?string $municipality): ?array
    {
        foreach (self::all()['municipalities'] as $m) {
            if ($m['name'] === $municipality) {
                return $m;
            }
        }

        return null;
    }

    public static function municipalityNames(): array
    {
        return array_column(self::all()['municipalities'], 'name');
    }

    /** Validation rule for `barangay`: it must belong to the chosen `city`. */
    public static function barangayRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $city = request()->input('city', request()->route('gym')?->city);

            if ($value !== null && ! in_array($value, self::find($city)['barangays'] ?? [], true)) {
                $fail('Pick a barangay of the chosen city or municipality.');
            }
        };
    }

    /** Province is always Cavite; zip follows the city or municipality (never taken from the client). */
    public static function apply(array $data): array
    {
        $data['province'] = self::PROVINCE;

        if (array_key_exists('city', $data)) {
            $data['zip_code'] = self::find($data['city'])['zip'] ?? null;
        }

        return $data;
    }
}
