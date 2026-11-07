<?php

namespace Tests\Unit;

use App\Support\DecisionSupport\Metrics;
use App\Support\DecisionSupport\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DecisionSupportPeriodTest extends TestCase
{
    public function test_same_day_leap_and_year_boundaries_are_half_open_and_copy_safe(): void
    {
        foreach ([['2024-02-29', '2024-03-01'], ['2026-12-31', '2027-01-01']] as [$day, $next]) {
            $period = new Period($day, $day);
            $this->assertSame($day.' 00:00:00', $period->start()->format('Y-m-d H:i:s'));
            $this->assertSame($next.' 00:00:00', $period->endExclusive()->format('Y-m-d H:i:s'));
            $this->assertSame('Asia/Manila', $period->start()->timezoneName);
            $period->start()->addYear();
            $period->endExclusive()->addYear();
            $this->assertSame($day, $period->from());
            $this->assertSame($day, $period->to());
        }
    }

    public function test_reversed_support_range_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Period('2026-11-02', '2026-11-01');
    }

    public function test_request_requires_exact_valid_and_ordered_dates_without_range_cap(): void
    {
        foreach ([[], ['from' => '2026-02-30', 'to' => '2026-03-01'],
            ['from' => '2026-1-01', 'to' => '2026-11-07'],
            ['from' => '2026-11-02', 'to' => '2026-11-01']] as $query) {
            try {
                Period::fromRequest(Request::create('/', 'GET', $query));
                $this->fail('Invalid range was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $period = Period::fromRequest(Request::create('/', 'GET', ['from' => '2000-01-01', 'to' => '2026-11-07']));
        $this->assertSame('2000-01-01', $period->from());
    }

    public function test_month_keys_and_trailing_windows_use_manila_completed_months(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-30 23:59:59', 'Asia/Manila'));
        try {
            $period = new Period('2026-01-01', '2027-01-01');
            $window = $period->trailingMonths(12, false);
            $this->assertSame('2025-12-01', $window->from());
            $this->assertSame('2026-11-30', $window->to());
            $this->assertCount(12, $window->monthKeys());
            $this->assertSame('2026-10-31', $period->trailingMonths(12, true)->to());
            $this->assertSame('2026-09-30', (new Period('2026-01-01', '2026-09-30'))->trailingMonths(12, true)->to());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rates_distinguish_missing_denominators_and_zero(): void
    {
        $this->assertNull(Metrics::rate(0, 0));
        $this->assertSame(0.0, Metrics::rate(0, 3));
        $this->assertSame(66.67, Metrics::rate(2, 3));
        $this->assertSame(-25.0, Metrics::rate(-1, 4));
    }
}
