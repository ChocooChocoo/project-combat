<?php

namespace App\Support;

/** Reads the tier rules in config/verification.php. */
class Verification
{
    /** @return list<array{types: list<string>, min: int}> */
    public static function requirements(int $tier): array
    {
        return config("verification.tiers.{$tier}.requirements", []);
    }

    /** @return list<string> */
    public static function typesFor(int $tier): array
    {
        return array_merge([], ...array_column(self::requirements($tier), 'types'));
    }

    /** @return list<string> */
    public static function checklistFor(int $tier): array
    {
        return array_keys(array_filter(
            config('verification.checklist'),
            fn (array $item) => in_array($tier, $item['tiers'], true),
        ));
    }

    /** Laravel validation rules for one file of this document type. */
    public static function fileRules(string $type): array
    {
        $kind = config('verification.kinds.'.config("verification.documents.{$type}.kind", 'document'));

        return ['required', 'file', 'mimes:'.implode(',', $kind['mimes']), 'max:'.$kind['max_kb']];
    }

    public static function maxKb(string $type): int
    {
        return config('verification.kinds.'.config("verification.documents.{$type}.kind", 'document').'.max_kb');
    }
}
