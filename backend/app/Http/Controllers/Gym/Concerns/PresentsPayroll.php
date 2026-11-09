<?php

namespace App\Http\Controllers\Gym\Concerns;

use App\Models\Gym;
use App\Models\PayrollApproval;
use App\Models\PayrollLine;
use App\Models\PayrollLineItem;
use App\Models\PayrollRun;

/** Shared snake_case response shapes for the payroll screens. */
trait PresentsPayroll
{
    protected function runSummary(Gym $gym, PayrollRun $run): array
    {
        $headcount = $run->lines_count
            ?? ($run->relationLoaded('lines') ? $run->lines->count() : $run->lines()->count());

        return [
            'id' => $run->id,
            'gym_id' => $run->gym_id,
            'starts_on' => $run->starts_on->toDateString(),
            'ends_on' => $run->ends_on->toDateString(),
            'status' => $run->status->value,
            'steps' => $run->steps ?: $gym->payrollSteps(),
            'current_step' => $run->current_step,
            'round' => $run->round,
            'frequency' => $gym->payroll_frequency ?: 'semi_monthly',
            'prepared_by_id' => $run->prepared_by,
            'prepared_by_name' => $this->personName($run->preparedBy),
            'submitted_at' => $run->submitted_at?->toIso8601String(),
            'computed_at' => $run->computed_at?->toIso8601String(),
            'released_at' => $run->released_at?->toIso8601String(),
            'released_by_id' => $run->released_by,
            'released_by_name' => $this->personName($run->releasedBy),
            'self_released' => $run->self_released,
            'rejection_reason' => $run->rejection_reason,
            'gross_total' => $run->gross_total,
            'employee_statutory_total' => $run->employee_statutory_total,
            'tax_total' => $run->tax_total,
            'net_total' => $run->net_total,
            'employer_statutory_total' => $run->employer_statutory_total,
            'headcount' => $headcount,
        ];
    }

    protected function linePayload(PayrollLine $line): array
    {
        return [
            'id' => $line->id,
            'run_id' => $line->payroll_run_id,
            'user_id' => $line->user_id,
            'name' => $line->name,
            'job_title' => $line->job_title,
            'pay_basis' => $line->pay_basis,
            'employment_type' => $line->employment_type,
            'monthly_rate' => $line->monthly_rate,
            'per_session_rate' => $line->per_session_rate,
            'daily_rate' => $line->daily_rate,
            'minute_rate' => $line->minute_rate,
            'scheduled_days' => $line->scheduled_days,
            'days_worked' => $line->days_worked,
            'days_present' => $line->days_worked - $line->days_late,
            'days_late' => $line->days_late,
            'days_absent' => $line->days_absent,
            'paid_leave_days' => $line->paid_leave_days,
            'unpaid_leave_days' => $line->unpaid_leave_days,
            'holiday_days' => $line->holiday_days,
            'late_minutes' => $line->late_minutes,
            'undertime_minutes' => $line->undertime_minutes,
            'overtime_minutes' => $line->overtime_minutes,
            'sessions_count' => $line->sessions_count,
            'base_pay' => $line->base_pay,
            'attendance_deduction' => $line->attendance_deduction,
            'holiday_premium' => $line->holiday_premium,
            'overtime_pay' => $line->overtime_pay,
            'session_pay' => $line->session_pay,
            'gross_pay' => $line->gross_pay,
            'sss_employee' => $line->sss_employee,
            'philhealth_employee' => $line->philhealth_employee,
            'pagibig_employee' => $line->pagibig_employee,
            'tax' => $line->tax,
            'net_pay' => $line->net_pay,
            'sss_employer' => $line->sss_employer,
            'ec_employer' => $line->ec_employer,
            'philhealth_employer' => $line->philhealth_employer,
            'pagibig_employer' => $line->pagibig_employer,
            'warnings' => $line->warnings ?? [],
            'statutory_rate_ids' => $line->statutory_rate_ids ?? [],
            'items' => $line->items->map(fn (PayrollLineItem $item) => $this->itemPayload($item))->values()->all(),
        ];
    }

    protected function itemPayload(PayrollLineItem $item): array
    {
        return [
            'id' => $item->id,
            'kind' => $item->kind,
            'label' => $item->label,
            'quantity' => $item->quantity,
            'rate' => $item->rate,
            'amount' => $item->amount,
            'ref' => $item->ref,
        ];
    }

    protected function approvalPayload(PayrollApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'round' => $approval->round,
            'step_index' => $approval->step_index,
            'step_key' => $approval->step_key,
            'user_id' => $approval->user_id,
            'user_name' => $this->personName($approval->user),
            'decision' => $approval->decision,
            'reason' => $approval->reason,
            'decided_at' => $approval->decided_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function runWarnings(PayrollRun $run): array
    {
        $warnings = [];

        foreach ($run->lines as $line) {
            foreach (($line->warnings ?? []) as $warning) {
                $warnings[] = [
                    'line_id' => $line->id,
                    'user_id' => $line->user_id,
                    'user_name' => $line->name,
                    ...$warning,
                ];
            }
        }

        if ($run->isDraft() && $run->lines->isEmpty()) {
            $warnings[] = [
                'line_id' => null,
                'user_id' => null,
                'user_name' => null,
                'code' => 'no_lines',
                'message' => 'Compute this payroll run before submitting it.',
                'blocking' => true,
            ];
        }

        return $warnings;
    }

    /**
     * R-62: a released payslip carries the stored line breakdown, not just the
     * gross and net totals. The sheet prints these figures directly.
     */
    protected function payslipRow(PayrollLine $line): array
    {
        $run = $line->run;

        return [
            'id' => $line->id,
            'run_id' => $line->payroll_run_id,
            'gym_id' => $line->gym_id,
            'starts_on' => $run?->starts_on?->toDateString(),
            'ends_on' => $run?->ends_on?->toDateString(),
            'released_at' => $run?->released_at?->toIso8601String(),
            'user_id' => $line->user_id,
            'user_name' => $line->name,
            'name' => $line->name,
            'job_title' => $line->job_title,
            'pay_basis' => $line->pay_basis,
            'employment_type' => $line->employment_type,
            'scheduled_days' => $line->scheduled_days,
            'days_worked' => $line->days_worked,
            'days_present' => $line->days_worked - $line->days_late,
            'days_late' => $line->days_late,
            'days_absent' => $line->days_absent,
            'paid_leave_days' => $line->paid_leave_days,
            'unpaid_leave_days' => $line->unpaid_leave_days,
            'late_minutes' => $line->late_minutes,
            'undertime_minutes' => $line->undertime_minutes,
            'overtime_minutes' => $line->overtime_minutes,
            'sessions_count' => $line->sessions_count,
            'base_pay' => $line->base_pay,
            'attendance_deduction' => $line->attendance_deduction,
            'holiday_premium' => $line->holiday_premium,
            'overtime_pay' => $line->overtime_pay,
            'session_pay' => $line->session_pay,
            'gross_pay' => $line->gross_pay,
            'sss_employee' => $line->sss_employee,
            'philhealth_employee' => $line->philhealth_employee,
            'pagibig_employee' => $line->pagibig_employee,
            'tax' => $line->tax,
            'net_pay' => $line->net_pay,
            'sss_employer' => $line->sss_employer,
            'ec_employer' => $line->ec_employer,
            'philhealth_employer' => $line->philhealth_employer,
            'pagibig_employer' => $line->pagibig_employer,
            'warnings' => $line->warnings ?? [],
        ];
    }

    private function personName(?object $person): ?string
    {
        if (! $person) {
            return null;
        }

        return trim("{$person->first_name} {$person->last_name}");
    }
}
