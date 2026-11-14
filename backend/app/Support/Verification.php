<?php

namespace App\Support;

use App\Models\Gym;
use Illuminate\Support\Collection;

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

    /** (R-132) Required files the admin has not opened yet. */
    public static function unopened(Gym $gym): Collection
    {
        return $gym->documents()->whereIn('type', self::typesFor($gym->tier ?? 0))->whereNull('admin_viewed_at')->get()
            ->filter(fn ($d) => config("verification.documents.{$d->type}.must_open", true))->values();
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
