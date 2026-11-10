<?php

namespace App\Support\Payroll;

use App\Enums\EmploymentType;
use Illuminate\Support\Carbon;

/**
 * R-59, R-60: pure payroll arithmetic. Inputs are already loaded snapshots;
 * everything happens in integer centavos and this class never touches the database.
 */
class Calculator
{
    public function __construct(private Statutory $statutory) {}

    /**
     * @param  array<string, mixed>  $period
     * @param  list<array<string, mixed>>  $employees
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<string, array<string, mixed>>  $rates
     * @return array{lines: list<array<string, mixed>>}
     */
    public function calculate(array $period, array $employees, array $inputs, array $rates): array
    {
        $lines = [];

        foreach ($employees as $employee) {
            $lines[] = $this->line($period, $employee, $inputs[$employee['user_id']] ?? [], $rates);
        }

        return ['lines' => $lines];
    }

    public function dailyRate(int $monthlyRateCents, int $annualFactor): int
    {
        return Money::roundHalfUp($annualFactor > 0 ? ($monthlyRateCents * 12) / $annualFactor : 0);
    }

    public function minuteRate(int $dailyRateCents, int $shiftMinutes): int
    {
        return Money::roundHalfUp($dailyRateCents / max(1, $shiftMinutes));
    }

    public function roundHalfUp(float|int $value): int
    {
        return Money::roundHalfUp($value);
    }

    public function holidayAmount(int $dailyRateCents, string $kind): int
    {
        $premium = (string) $kind === 'holiday_regular'
            ? (float) config('payroll.holiday_premiums.regular', 1.00)
            : (float) config('payroll.holiday_premiums.special', 0.30);

        return Money::roundHalfUp($dailyRateCents * $premium);
    }

    public function overtimeAmount(int $minuteRateCents, int $minutes): int
    {
        return Money::roundHalfUp($minuteRateCents * $minutes * (float) config('payroll.overtime_multiplier', 1.25));
    }

    /**
     * @param  array<string, mixed>  $period
     * @param  array<string, mixed>  $employee
     * @param  array<string, mixed>  $input
     * @param  array<string, array<string, mixed>>  $rates
     * @return array<string, mixed>
     */
    private function line(array $period, array $employee, array $input, array $rates): array
    {
        $frequency = (string) ($period['frequency'] ?? 'semi_monthly');
        $payBasis = $employee['pay_basis'] ?? null;
        $employmentType = (string) ($employee['employment_type'] ?? EmploymentType::Employee->value);
        $usesMonthly = in_array($payBasis, ['monthly', 'both'], true);
        $usesPerSession = in_array($payBasis, ['per_session', 'both'], true);
        $monthlyRate = $employee['monthly_rate_cents'] ?? null;
        $perSessionRate = $employee['per_session_rate_cents'] ?? null;

        $warnings = [];
        $items = [];

        if ($payBasis === null || $payBasis === '') {
            $warnings[] = $this->warning('missing_pay_basis', 'No pay basis is set for this employee.', empty($employee['ended_on']));
        }

        if ($usesMonthly && $monthlyRate === null) {
            $warnings[] = $this->warning('missing_monthly_rate', 'The monthly rate is missing.', true);
        }

        if ($usesPerSession && $perSessionRate === null) {
            $warnings[] = $this->warning('missing_per_session_rate', 'The per-session rate is missing.', true);
        }

        $dailyRate = $usesMonthly && $monthlyRate !== null
            ? $this->dailyRate((int) $monthlyRate, (int) ($employee['annual_factor'] ?? 1))
            : 0;
        $minuteRate = $dailyRate > 0
            ? $this->minuteRate($dailyRate, (int) ($employee['shift_minutes'] ?? 480))
            : 0;

        $days = $input['days'] ?? [];
        $basePay = 0;

        if ($usesMonthly && $monthlyRate !== null) {
            if ($employee['full_period'] ?? true) {
                $basePay = $frequency === 'monthly'
                    ? (int) $monthlyRate
                    : Money::roundHalfUp($monthlyRate / 2);
            } else {
                $paidDays = 0;
                foreach ($days as $day) {
                    if (($day['in_employment'] ?? false) && ($day['kind'] ?? null) === 'working') {
                        $paidDays++;
                    }
                }
                $basePay = $dailyRate * $paidDays;
            }

            if ($basePay !== 0) {
                $items[] = $this->item('base_pay', 'Base pay', null, null, $basePay);
            }
        }

        $scheduledDays = 0;
        $daysWorked = 0;
        $daysLate = 0;
        $daysAbsent = 0;
        $paidLeaveDays = 0;
        $unpaidLeaveDays = 0;
        $holidayDays = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $attendanceDeduction = 0;
        $holidayPremium = 0;
        $unrecorded = [];

        foreach ($days as $day) {
            if (! ($day['in_employment'] ?? false)) {
                continue;
            }

            $kind = (string) ($day['kind'] ?? 'rest');
            $attendance = $day['attendance'] ?? null;
            $worked = in_array($attendance, ['present', 'late'], true);

            if ($kind === 'holiday_regular' || $kind === 'holiday_special') {
                $holidayDays++;

                if ($worked && $dailyRate > 0) {
                    $amount = $this->holidayAmount($dailyRate, $kind);
                    if ($amount !== 0) {
                        $holidayPremium += $amount;
                        $items[] = $this->item(
                            'holiday_premium',
                            ($kind === 'holiday_regular' ? 'Regular holiday' : 'Special holiday').' premium on '.$this->dateLabel($day['date']),
                            null,
                            null,
                            $amount,
                            ['date' => $day['date']],
                        );
                    }
                }

                continue;
            }

            if ($kind !== 'working' || ($day['future'] ?? false)) {
                continue;
            }

            $scheduledDays++;
            $leave = $day['leave'] ?? null;

            if ($leave === 'paid') {
                $paidLeaveDays++;

                continue;
            }

            if ($leave === 'unpaid') {
                $unpaidLeaveDays++;
                if ($dailyRate > 0) {
                    $attendanceDeduction += $dailyRate;
                    $items[] = $this->item(
                        'attendance_deduction',
                        'Unpaid leave on '.$this->dateLabel($day['date']),
                        null,
                        null,
                        -$dailyRate,
                        ['date' => $day['date'], 'reason' => 'unpaid_leave'],
                    );
                }

                continue;
            }

            if ($worked) {
                $daysWorked++;
                if ($attendance === 'late') {
                    $daysLate++;
                }
                $lateMinutes += (int) ($day['late_minutes'] ?? 0);
                $undertimeMinutes += (int) ($day['undertime_minutes'] ?? 0);

                if ($day['missing_clock_out'] ?? false) {
                    $warnings[] = $this->warning(
                        'missing_clock_out',
                        'A missing clock-out was recorded on '.$this->dateLabel($day['date']).'.',
                        false,
                    );
                }

                continue;
            }

            $daysAbsent++;

            if ($attendance === null) {
                $unrecorded[] = $this->dateLabel($day['date']);
            }

            if ($dailyRate > 0) {
                $attendanceDeduction += $dailyRate;
                $items[] = $this->item(
                    'attendance_deduction',
                    'Absence on '.$this->dateLabel($day['date']),
                    null,
                    null,
                    -$dailyRate,
                    ['date' => $day['date'], 'reason' => 'absence'],
                );
            }
        }

        if ($unrecorded !== []) {
            $warnings[] = $this->warning(
                'missing_attendance',
                count($unrecorded).' working day(s) had no attendance record and were treated as absent: '.implode(', ', $unrecorded).'.',
                false,
            );
        }

        if ($lateMinutes > 0 && $minuteRate > 0) {
            $amount = Money::multiply($minuteRate, $lateMinutes);
            $attendanceDeduction += $amount;
            $items[] = $this->item('attendance_deduction', "Late ({$lateMinutes} minutes)", $lateMinutes, $minuteRate, -$amount, ['reason' => 'late']);
        }

        if ($undertimeMinutes > 0 && $minuteRate > 0) {
            $amount = Money::multiply($minuteRate, $undertimeMinutes);
            $attendanceDeduction += $amount;
            $items[] = $this->item('attendance_deduction', "Undertime ({$undertimeMinutes} minutes)", $undertimeMinutes, $minuteRate, -$amount, ['reason' => 'undertime']);
        }

        $overtimePay = 0;
        $overtimeMinutes = 0;

        foreach ($input['overtime'] ?? [] as $request) {
            $minutes = (int) ($request['approved_minutes'] ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            $overtimeMinutes += $minutes;
            if ($minuteRate > 0) {
                $amount = $this->overtimeAmount($minuteRate, $minutes);
                $overtimePay += $amount;
                $items[] = $this->item(
                    'overtime_pay',
                    'Approved overtime on '.$this->dateLabel($request['work_date']),
                    $minutes,
                    $minuteRate,
                    $amount,
                    ['overtime_request_id' => $request['id'] ?? null, 'date' => $request['work_date']],
                );
            }
        }

        $sessionsCount = 0;
        $sessionPay = 0;

        foreach ($input['sessions'] ?? [] as $session) {
            if (! ($session['counted'] ?? false)) {
                if (filled($session['reason'] ?? null)) {
                    $warnings[] = $this->warning(
                        'excluded_session',
                        'Session '.$this->dateLabel($session['ends_at'] ?? null).' excluded: '.$session['reason'],
                        false,
                    );
                }

                continue;
            }

            $sessionsCount++;

            if ($usesPerSession && $perSessionRate !== null) {
                $sessionPay += (int) $perSessionRate;
                $items[] = $this->item(
                    'session_pay',
                    'Session: '.($session['name'] ?? 'Session').' on '.$this->dateLabel($session['ends_at'] ?? null),
                    1,
                    (int) $perSessionRate,
                    (int) $perSessionRate,
                    ['training_session_id' => $session['id'] ?? null],
                );
            }
        }

        // The daily rate is annualised over the whole year, so a period with
        // more scheduled days than the half-month average can deduct more than
        // the base. Pay stops at zero; it never becomes a debt to the gym.
        $grossPay = max(0, $basePay + $holidayPremium + $overtimePay + $sessionPay - $attendanceDeduction);

        $sssEmployee = 0;
        $philhealthEmployee = 0;
        $pagibigEmployee = 0;
        $tax = 0;
        $sssEmployer = 0;
        $ecEmployer = 0;
        $philhealthEmployer = 0;
        $pagibigEmployer = 0;
        $rateIds = [];

        if ($employmentType !== EmploymentType::Contractor->value) {
            $missing = $this->statutory->missingKinds($rates, $frequency);

            if ($missing !== []) {
                foreach ($missing as $kind) {
                    $warnings[] = $this->warning(
                        'missing_statutory_rate',
                        "No {$kind->label()} rate is effective for this period.",
                        true,
                    );
                }
            } else {
                $computed = $this->statutory->compute($grossPay, $frequency, $rates);
                $sssEmployee = (int) $computed['sss_employee'];
                $philhealthEmployee = (int) $computed['philhealth_employee'];
                $pagibigEmployee = (int) $computed['pagibig_employee'];
                $tax = (int) $computed['tax'];
                $sssEmployer = (int) $computed['sss_employer'];
                $ecEmployer = (int) $computed['ec_employer'];
                $philhealthEmployer = (int) $computed['philhealth_employer'];
                $pagibigEmployer = (int) $computed['pagibig_employer'];
                $rateIds = $computed['rate_ids'];

                // Defence in depth: no employee deduction when there is no pay.
                if ($grossPay <= 0) {
                    $sssEmployee = 0;
                    $philhealthEmployee = 0;
                    $pagibigEmployee = 0;
                    $tax = 0;
                }

                // Statutory minimums can exceed a very small gross after
                // absences. Deductions may not exceed the pay being deducted
                // from, so cap them and leave an explicit warning.
                $employeeTotal = $sssEmployee + $philhealthEmployee + $pagibigEmployee + $tax;
                if ($grossPay > 0 && $employeeTotal > $grossPay) {
                    $remaining = $grossPay;
                    $tax = min($tax, $remaining);
                    $remaining -= $tax;
                    $pagibigEmployee = min($pagibigEmployee, $remaining);
                    $remaining -= $pagibigEmployee;
                    $philhealthEmployee = min($philhealthEmployee, $remaining);
                    $remaining -= $philhealthEmployee;
                    $sssEmployee = min($sssEmployee, $remaining);

                    $warnings[] = $this->warning(
                        'statutory_capped_at_gross',
                        'Employee statutory contributions exceeded gross pay and were capped at the gross amount.',
                        false,
                    );
                }

                foreach ([
                    ['sss_employee', 'SSS (employee)', $sssEmployee],
                    ['philhealth_employee', 'PhilHealth (employee)', $philhealthEmployee],
                    ['pagibig_employee', 'Pag-IBIG (employee)', $pagibigEmployee],
                    ['tax', 'Withholding tax', $tax],
                ] as [$itemKind, $label, $amount]) {
                    if ($amount !== 0) {
                        $items[] = $this->item($itemKind, $label, null, null, -$amount);
                    }
                }

                foreach ([
                    ['sss_employer', 'SSS (employer)', $sssEmployer],
                    ['ec_employer', 'EC (employer)', $ecEmployer],
                    ['philhealth_employer', 'PhilHealth (employer)', $philhealthEmployer],
                    ['pagibig_employer', 'Pag-IBIG (employer)', $pagibigEmployer],
                ] as [$itemKind, $label, $amount]) {
                    if ($amount !== 0) {
                        $items[] = $this->item($itemKind, $label, null, null, $amount);
                    }
                }
            }
        }

        $netPay = max(0, $grossPay - $sssEmployee - $philhealthEmployee - $pagibigEmployee - $tax);

        return [
            'user_id' => $employee['user_id'],
            'name' => $employee['name'] ?? '',
            'job_title' => $employee['job_title'] ?? null,
            'pay_basis' => $payBasis,
            'employment_type' => $employmentType,
            'monthly_rate' => $monthlyRate,
            'per_session_rate' => $perSessionRate,
            'daily_rate' => $dailyRate,
            'minute_rate' => $minuteRate,
            'scheduled_days' => $scheduledDays,
            'days_worked' => $daysWorked,
            'days_late' => $daysLate,
            'days_absent' => $daysAbsent,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'holiday_days' => $holidayDays,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'sessions_count' => $sessionsCount,
            'base_pay' => $basePay,
            'attendance_deduction' => $attendanceDeduction,
            'holiday_premium' => $holidayPremium,
            'overtime_pay' => $overtimePay,
            'session_pay' => $sessionPay,
            'gross_pay' => $grossPay,
            'sss_employee' => $sssEmployee,
            'philhealth_employee' => $philhealthEmployee,
            'pagibig_employee' => $pagibigEmployee,
            'tax' => $tax,
            'net_pay' => $netPay,
            'sss_employer' => $sssEmployer,
            'ec_employer' => $ecEmployer,
            'philhealth_employer' => $philhealthEmployer,
            'pagibig_employer' => $pagibigEmployer,
            'warnings' => $warnings,
            'statutory_rate_ids' => $rateIds,
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function item(
        string $kind,
        string $label,
        int|float|null $quantity,
        ?int $rate,
        int $amount,
        ?array $ref = null,
    ): array {
        return [
            'kind' => $kind,
            'label' => $label,
            'quantity' => $quantity,
            'rate' => $rate,
            'amount' => $amount,
            'ref' => $ref,
        ];
    }

    /** @return array{code: string, message: string, blocking: bool} */
    private function warning(string $code, string $message, bool $blocking): array
    {
        return ['code' => $code, 'message' => $message, 'blocking' => $blocking];
    }

    private function dateLabel(?string $date): string
    {
        if ($date === null || $date === '') {
            return 'an unknown date';
        }

        return Carbon::parse($date)->format('M j, Y');
    }
}
