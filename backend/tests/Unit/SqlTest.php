<?php

namespace Tests\Unit;

use App\Support\Sql;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlTest extends TestCase
{
    public function test_month_expressions_are_unchanged(): void
    {
        $this->assertSame("strftime('%Y-%m', x.date)", Sql::monthFor('sqlite', 'x.date'));
        $this->assertSame("DATE_FORMAT(x.date, '%Y-%m')", Sql::monthFor('mysql', 'x.date'));
    }

    public function test_weekday_and_hour_driver_branches_and_real_sunday_midnight(): void
    {
        foreach (['mysql', 'mariadb'] as $driver) {
            $this->assertSame('(DAYOFWEEK(x.date) - 1)', Sql::weekdayFor($driver, 'x.date'));
            $this->assertSame('HOUR(x.date)', Sql::hourFor($driver, 'x.date'));
        }
        $this->assertSame("CAST(strftime('%w', x.date) AS INTEGER)", Sql::weekdayFor('sqlite', 'x.date'));
        $this->assertSame("CAST(strftime('%H', x.date) AS INTEGER)", Sql::hourFor('sqlite', 'x.date'));
        $row = DB::query()->fromSub(DB::query()->selectRaw("'2026-11-01 00:00:00' as date"), 'x')
            ->selectRaw(Sql::weekday('x.date').' as weekday, '.Sql::hour('x.date').' as hour')->first();
        $this->assertSame(0, $row->weekday);
        $this->assertSame(0, $row->hour);
    }

    public function test_unsafe_identifiers_and_unsupported_drivers_are_rejected(): void
    {
        foreach (['monthFor', 'weekdayFor', 'hourFor'] as $method) {
            foreach ([['sqlite', 'x); DROP TABLE users'], ['pgsql', 'x.date']] as [$driver, $column]) {
                try {
                    Sql::$method($driver, $column);
                    $this->fail('Unsafe expression accepted.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }
        }
    }
}
