<?php

namespace Tests\Unit;

use App\Support\Payroll\Calculator;
use App\Support\Payroll\Statutory;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** R-59: the pure payroll arithmetic, proved in integer centavos. */
class PayrollCalculatorTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new Calculator(new Statutory);
    }

    public function test_a_full_semi_monthly_period_pays_half_the_monthly_rate(): void
    {
        $line = $this->calculate($this->employee(['pay_basis' => 'monthly']))[0];

        $this->assertSame(2_250_000, $line['base_pay']);
        $this->assertSame(206_897, $line['daily_rate']);
        $this->assertSame(431, $line['minute_rate']);
        $this->assertSame(0, $line['session_pay']);
        $this->assertSame(0, $line['attendance_deduction']);
    }

    public function test_a_per_session_employee_is_paid_only_for_counted_sessions(): void
    {
        $line = $this->calculate(
            $this->employee(['pay_basis' => 'per_session', 'monthly_rate_cents' => null, 'per_session_rate_cents' => 50_000]),
            [],
            [],
            [
                ['id' => 1, 'name' => 'Boxing', 'ends_at' => '2026-11-03 10:00:00', 'counted' => true, 'reason' => null],
                ['id' => 2, 'name' => 'Muay Thai', 'ends_at' => '2026-11-04 10:00:00', 'counted' => true, 'reason' => null],
                ['id' => 3, 'name' => 'BJJ', 'ends_at' => '2026-11-05 10:00:00', 'counted' => true, 'reason' => null],
                ['id' => 4, 'name' => 'Boxing', 'ends_at' => '2026-11-06 10:00:00', 'counted' => true, 'reason' => null],
                ['id' => 5, 'name' => 'Excluded', 'ends_at' => '2026-11-07 10:00:00', 'counted' => false, 'reason' => 'No booking was present or late.'],
            ],
        )[0];

        $this->assertSame(0, $line['base_pay']);
        $this->assertSame(4, $line['sessions_count']);
        $this->assertSame(200_000, $line['session_pay']);
        $this->assertSame(4, collect($line['items'])->where('kind', 'session_pay')->count());
        $this->assertSame('excluded_session', collect($line['warnings'])->first()['code']);
    }

    public function test_both_pay_bases_add_the_monthly_base_and_session_pay(): void
    {
        $line = $this->calculate(
            $this->employee(['pay_basis' => 'both']),
            [],
            [],
            [['id' => 1, 'name' => 'Boxing', 'ends_at' => '2026-11-03 10:00:00', 'counted' => true, 'reason' => null]],
        )[0];

        $this->assertSame(2_250_000, $line['base_pay']);
        $this->assertSame(50_000, $line['session_pay']);
    }

    public function test_a_mid_period_start_prorates_only_the_days_inside_employment(): void
    {
        $days = [];
        for ($index = 0; $index < 10; $index++) {
            $days[] = $this->day('2026-11-'.str_pad((string) ($index + 2), 2, '0', STR_PAD_LEFT), 'working', $index >= 3);
        }

        $line = $this->calculate($this->employee(['full_period' => false]), $days)[0];

        $this->assertSame(206_897 * 7, $line['base_pay']);
        $this->assertSame(7, $line['scheduled_days']);
        $this->assertSame(7, $line['days_worked']);
    }

    public function test_a_mid_period_end_prorates_only_the_days_inside_employment(): void
    {
        $days = [];
        for ($index = 0; $index < 10; $index++) {
            $days[] = $this->day('2026-11-'.str_pad((string) ($index + 2), 2, '0', STR_PAD_LEFT), 'working', $index < 7);
        }

        $line = $this->calculate($this->employee(['full_period' => false]), $days)[0];

        $this->assertSame(206_897 * 7, $line['base_pay']);
        $this->assertSame(7, $line['scheduled_days']);
        $this->assertSame(7, $line['days_worked']);
    }

    public function test_paid_leave_does_not_reduce_pay_and_unpaid_leave_does(): void
    {
        $days = [];
        for ($index = 0; $index < 10; $index++) {
            $leave = $index < 2 ? 'paid' : ($index === 2 ? 'unpaid' : null);
            $days[] = $this->day(
                '2026-11-'.str_pad((string) ($index + 2), 2, '0', STR_PAD_LEFT),
                'working',
                true,
                $leave === null ? 'present' : null,
                $leave,
            );
        }

        $line = $this->calculate($this->employee(['pay_basis' => 'monthly']), $days)[0];

        $this->assertSame(2, $line['paid_leave_days']);
        $this->assertSame(1, $line['unpaid_leave_days']);
        $this->assertSame(7, $line['days_worked']);
        $this->assertSame(206_897, $line['attendance_deduction']);
        $this->assertSame(2_250_000 - 206_897, $line['gross_pay']);
    }

    public function test_absence_reduces_pay_by_the_daily_rate(): void
    {
        $days = [];
        for ($index = 0; $index < 10; $index++) {
            $days[] = $this->day(
                '2026-11-'.str_pad((string) ($index + 2), 2, '0', STR_PAD_LEFT),
                'working',
                true,
                $index < 3 ? 'absent' : 'present',
            );
        }

        $line = $this->calculate($this->employee(['pay_basis' => 'monthly']), $days)[0];

        $this->assertSame(3, $line['days_absent']);
        $this->assertSame(206_897 * 3, $line['attendance_deduction']);
        $this->assertSame(2_250_000 - (206_897 * 3), $line['gross_pay']);
    }

    public function test_absence_deduction_greater_than_base_is_floored_at_zero(): void
    {
        $employee = $this->employee(['pay_basis' => 'monthly']);
        $days = $this->workingDays('2026-10-16', '2026-10-31', 'absent');

        $rates = $this->rates();
        $rates['philhealth']['brackets'] = [['min' => '0.00', 'max' => null, 'employee' => '250.00', 'employer' => '250.00']];
        $rates['pagibig']['brackets'] = [['min' => '0.00', 'max' => null, 'employee' => '100.00', 'employer' => '100.00']];

        $line = $this->calculator->calculate(
            ['start' => '2026-10-16', 'end' => '2026-10-31', 'frequency' => 'semi_monthly', 'days' => 16],
            [$employee],
            [$employee['user_id'] => ['days' => $days, 'overtime' => [], 'sessions' => []]],
            $rates,
        )['lines'][0];

        $this->assertSame(11, $line['days_absent']);
        $this->assertSame(2_250_000, $line['base_pay']);
        $this->assertSame(206_897 * 11, $line['attendance_deduction']);
        $this->assertGreaterThan($line['base_pay'], $line['attendance_deduction']);
        $this->assertSame(0, $line['gross_pay']);
        $this->assertSame(0, $line['sss_employee']);
        $this->assertSame(0, $line['philhealth_employee']);
        $this->assertSame(0, $line['pagibig_employee']);
        $this->assertSame(0, $line['tax']);
        $this->assertSame(0, $line['net_pay']);
        $this->assertSame(
            0,
            collect($line['items'])->whereIn('kind', ['sss_employee', 'philhealth_employee', 'pagibig_employee', 'tax'])->count(),
        );
    }

    public function test_a_partial_absence_deduction_still_reduces_gross_and_applies_statutory(): void
    {
        $employee = $this->employee(['pay_basis' => 'monthly']);
        $days = $this->workingDays('2026-10-16', '2026-10-31');
        $days[0]['attendance'] = 'absent';

        $line = $this->calculator->calculate(
            ['start' => '2026-10-16', 'end' => '2026-10-31', 'frequency' => 'semi_monthly', 'days' => 16],
            [$employee],
            [$employee['user_id'] => ['days' => $days, 'overtime' => [], 'sessions' => []]],
            $this->rates(),
        )['lines'][0];

        $this->assertSame(1, $line['days_absent']);
        $this->assertSame(206_897, $line['attendance_deduction']);
        $this->assertSame(2_043_103, $line['gross_pay']);
        $this->assertSame(5_000, $line['sss_employee']);
        $this->assertSame(51_078, $line['philhealth_employee']);
        $this->assertSame(40_862, $line['pagibig_employee']);
        $this->assertSame(0, $line['tax']);
        $this->assertSame(1_946_163, $line['net_pay']);
    }

    public function test_employee_deductions_are_capped_at_gross_and_warned(): void
    {
        $employee = $this->employee(['pay_basis' => 'monthly', 'monthly_rate_cents' => 1_000_000]);
        $rates = $this->rates();
        $rates['sss']['brackets'] = [
            ['min' => '0.00', 'max' => null, 'employee' => '20000.00', 'employer' => '40000.00', 'ec' => '10.00'],
        ];

        $line = $this->calculator->calculate(
            ['start' => '2026-11-01', 'end' => '2026-11-15', 'frequency' => 'semi_monthly', 'days' => 15],
            [$employee],
            [$employee['user_id'] => ['days' => [], 'overtime' => [], 'sessions' => []]],
            $rates,
        )['lines'][0];

        $this->assertSame(500_000, $line['gross_pay']);
        $this->assertSame(
            500_000,
            $line['sss_employee'] + $line['philhealth_employee'] + $line['pagibig_employee'] + $line['tax'],
        );
        $this->assertSame(0, $line['net_pay']);
        $this->assertTrue(collect($line['warnings'])->contains(fn (array $warning) => $warning['code'] === 'statutory_capped_at_gross'));
    }

    public function test_late_and_undertime_reduce_pay_by_the_minute_rate(): void
    {
        $days = [
            $this->day('2026-11-02', 'working', true, 'late', null, 30, 0),
            $this->day('2026-11-03', 'working', true, 'late', null, 0, 15),
        ];

        $line = $this->calculate($this->employee(['pay_basis' => 'monthly']), $days)[0];

        $this->assertSame(30, $line['late_minutes']);
        $this->assertSame(15, $line['undertime_minutes']);
        $this->assertSame((30 * 431) + (15 * 431), $line['attendance_deduction']);
    }

    public function test_regular_and_special_holiday_premiums_apply_only_when_worked(): void
    {
        $days = [
            $this->day('2026-11-02', 'holiday_regular', true, 'present'),
            $this->day('2026-11-03', 'holiday_special', true, 'present'),
            $this->day('2026-11-04', 'holiday_special', true, null),
        ];

        $line = $this->calculate($this->employee(['pay_basis' => 'monthly']), $days)[0];

        $this->assertSame(3, $line['holiday_days']);
        $this->assertSame(206_897 + 62_069, $line['holiday_premium']);
        $this->assertSame(2, collect($line['items'])->where('kind', 'holiday_premium')->count());
    }

    public function test_only_approved_overtime_minutes_are_paid(): void
    {
        $line = $this->calculate(
            $this->employee(['pay_basis' => 'monthly']),
            [],
            [
                ['id' => 1, 'work_date' => '2026-11-03', 'approved_minutes' => 60],
                ['id' => 2, 'work_date' => '2026-11-04', 'approved_minutes' => 0],
            ],
        )[0];

        $this->assertSame(60, $line['overtime_minutes']);
        $this->assertSame(32_325, $line['overtime_pay']);
        $this->assertSame(1, collect($line['items'])->where('kind', 'overtime_pay')->count());
    }

    public function test_centavo_rounding_is_half_up_at_each_line_item(): void
    {
        $this->assertSame(3, $this->calculator->roundHalfUp(2.5));
        $this->assertSame(4, $this->calculator->overtimeAmount(1, 3));
        $this->assertSame(206_897, $this->calculator->dailyRate(4_500_000, 261));
        $this->assertSame(431, $this->calculator->minuteRate(206_897, 480));
    }

    public function test_a_contractor_gets_no_statutory_deductions(): void
    {
        $line = $this->calculate($this->employee(['pay_basis' => 'monthly', 'employment_type' => 'contractor']))[0];

        $this->assertSame(0, $line['sss_employee']);
        $this->assertSame(0, $line['philhealth_employee']);
        $this->assertSame(0, $line['pagibig_employee']);
        $this->assertSame(0, $line['tax']);
        $this->assertSame(0, $line['sss_employer']);
        $this->assertSame(0, $line['ec_employer']);
        $this->assertSame([], $line['statutory_rate_ids']);
        $this->assertSame(0, collect($line['items'])->whereIn('kind', ['sss_employee', 'philhealth_employee', 'pagibig_employee', 'tax'])->count());
    }

    public function test_missing_pay_basis_and_rate_are_blocking_warnings(): void
    {
        $line = $this->calculate($this->employee(['pay_basis' => null]))[0];
        $warnings = collect($line['warnings']);

        $this->assertTrue($warnings->contains(fn (array $warning) => $warning['code'] === 'missing_pay_basis' && $warning['blocking']));

        $line = $this->calculate($this->employee(['pay_basis' => 'monthly', 'monthly_rate_cents' => null]))[0];
        $warnings = collect($line['warnings']);

        $this->assertTrue($warnings->contains(fn (array $warning) => $warning['code'] === 'missing_monthly_rate' && $warning['blocking']));
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @param  list<array<string, mixed>>  $overtime
     * @param  list<array<string, mixed>>  $sessions
     * @return list<array<string, mixed>>
     */
    private function calculate(array $employee, array $days = [], array $overtime = [], array $sessions = []): array
    {
        return $this->calculator->calculate(
            ['start' => '2026-11-01', 'end' => '2026-11-15', 'frequency' => 'semi_monthly', 'days' => 15],
            [$employee],
            [$employee['user_id'] => ['days' => $days, 'overtime' => $overtime, 'sessions' => $sessions]],
            $this->rates(),
        )['lines'];
    }

    /** @return array<string, mixed> */
    private function employee(array $overrides = []): array
    {
        return [
            'user_id' => 1,
            'name' => 'Ella Santos',
            'job_title' => 'Coach',
            'pay_basis' => 'monthly',
            'employment_type' => 'employee',
            'monthly_rate_cents' => 4_500_000,
            'per_session_rate_cents' => 50_000,
            'started_on' => '2026-01-01',
            'ended_on' => null,
            'full_period' => true,
            'working_weekdays' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'annual_factor' => 261,
            'shift_minutes' => 480,
            ...$overrides,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function workingDays(string $from, string $to, string $attendance = 'present'): array
    {
        $days = [];
        $end = Carbon::parse($to);

        for ($day = Carbon::parse($from); $day->lessThanOrEqualTo($end); $day->addDay()) {
            if ($day->isWeekday()) {
                $days[] = $this->day($day->toDateString(), 'working', true, $attendance);
            }
        }

        return $days;
    }

    /** @return array<string, mixed> */
    private function day(
        string $date,
        string $kind = 'working',
        bool $inEmployment = true,
        ?string $attendance = 'present',
        ?string $leave = null,
        int $late = 0,
        int $undertime = 0,
    ): array {
        return [
            'date' => $date,
            'kind' => $kind,
            'in_employment' => $inEmployment,
            'attendance' => $attendance,
            'late_minutes' => $late,
            'undertime_minutes' => $undertime,
            'missing_clock_out' => false,
            'leave' => $leave,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function rates(): array
    {
        return [
            'sss' => [
                'id' => 1,
                'kind' => 'sss',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'employee' => '100.00', 'employer' => '190.00', 'ec' => '10.00'],
                ],
            ],
            'philhealth' => [
                'id' => 2,
                'kind' => 'philhealth',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'employee_rate' => 0.025, 'employer_rate' => 0.025],
                ],
            ],
            'pagibig' => [
                'id' => 3,
                'kind' => 'pagibig',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'employee_rate' => 0.02, 'employer_rate' => 0.02],
                ],
            ],
            'tax_semi_monthly' => [
                'id' => 4,
                'kind' => 'tax_semi_monthly',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'base' => '0.00', 'rate' => 0.0],
                ],
            ],
            'tax_monthly' => [
                'id' => 5,
                'kind' => 'tax_monthly',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'base' => '0.00', 'rate' => 0.0],
                ],
            ],
        ];
    }
}
