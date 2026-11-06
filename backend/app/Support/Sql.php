<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Database expressions whose spelling differs between SQLite and MySQL. */
final class Sql
{
    /** A YYYY-MM bucket expression for the configured connection. */
    public static function month(string $column): string
    {
        return self::monthFor(DB::connection()->getDriverName(), $column);
    }

    /** The same expression for a named driver, so both branches are testable. */
    public static function monthFor(string $driver, string $column): string
    {
        self::assertColumn($column);

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => throw new InvalidArgumentException("Month bucketing is not supported on {$driver}."),
        };
    }

    private static function assertColumn(string $column): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new InvalidArgumentException("Unsafe SQL column: {$column}");
        }
    }
}
