<?php

namespace App\Support\Payroll;

use App\Enums\GymPersonType;
use App\Enums\PayrollStatus;
use App\Enums\PayrollStep;
use App\Models\Expense;
use App\Models\Gym;
use App\Models\PayrollApproval;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\User;
use App\Notifications\PayrollDecided;
use App\Notifications\PayrollSubmitted;
use App\Notifications\PayslipReleased;
use App\Support\CurrentGym;
use App\Support\Finance\Expenses;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * R-61: the payroll state machine. Compute and submit only in draft, every
 * decision runs under a row lock, and the only path to released is approving
 * the final step of the chain.
 */
class Workflow
{
    public function __construct(
        private InputLoader $loader,
        private Calculator $calculator,
    ) {}

    public function compute(Gym $gym, PayrollRun $run, User $actor): PayrollRun
    {
        app(CurrentGym::class)->set($gym);

        return DB::transaction(function () use ($gym, $run) {
            $run = $this->lockedRun($gym, $run);
            abort_unless($run->status === PayrollStatus::Draft, 409, 'Only a draft payroll run can be computed.');

            $loaded = $this->loader->load($gym, $run->starts_on, $run->ends_on);
            $result = $this->calculator->calculate($loaded['period'], $loaded['employees'], $loaded['inputs'], $loaded['rates']);

            PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->delete();

            $grossTotal = 0;
            $employeeStatutoryTotal = 0;
            $taxTotal = 0;
            $netTotal = 0;
            $employerStatutoryTotal = 0;

            foreach ($result['lines'] as $line) {
                $record = PayrollLine::create([
                    'payroll_run_id' => $run->id,
                    'user_id' => $line['user_id'],
                    'name' => $line['name'],
                    'job_title' => $line['job_title'],
                    'pay_basis' => $line['pay_basis'],
                    'employment_type' => $line['employment_type'],
                    'monthly_rate' => $this->moneyOrNull($line['monthly_rate']),
                    'per_session_rate' => $this->moneyOrNull($line['per_session_rate']),
                    'daily_rate' => $this->moneyOrNull($line['daily_rate']),
                    'minute_rate' => $this->moneyOrNull($line['minute_rate']),
                    'scheduled_days' => $line['scheduled_days'],
                    'days_worked' => $line['days_worked'],
                    'days_late' => $line['days_late'],
                    'days_absent' => $line['days_absent'],
                    'paid_leave_days' => $line['paid_leave_days'],
                    'unpaid_leave_days' => $line['unpaid_leave_days'],
                    'holiday_days' => $line['holiday_days'],
                    'late_minutes' => $line['late_minutes'],
                    'undertime_minutes' => $line['undertime_minutes'],
                    'overtime_minutes' => $line['overtime_minutes'],
                    'sessions_count' => $line['sessions_count'],
                    'base_pay' => Money::toDecimal($line['base_pay']),
                    'attendance_deduction' => Money::toDecimal($line['attendance_deduction']),
                    'holiday_premium' => Money::toDecimal($line['holiday_premium']),
                    'overtime_pay' => Money::toDecimal($line['overtime_pay']),
                    'session_pay' => Money::toDecimal($line['session_pay']),
                    'gross_pay' => Money::toDecimal($line['gross_pay']),
                    'sss_employee' => Money::toDecimal($line['sss_employee']),
                    'philhealth_employee' => Money::toDecimal($line['philhealth_employee']),
                    'pagibig_employee' => Money::toDecimal($line['pagibig_employee']),
                    'tax' => Money::toDecimal($line['tax']),
                    'net_pay' => Money::toDecimal($line['net_pay']),
                    'sss_employer' => Money::toDecimal($line['sss_employer']),
                    'ec_employer' => Money::toDecimal($line['ec_employer']),
                    'philhealth_employer' => Money::toDecimal($line['philhealth_employer']),
                    'pagibig_employer' => Money::toDecimal($line['pagibig_employer']),
                    'warnings' => $line['warnings'],
                    'statutory_rate_ids' => $line['statutory_rate_ids'],
                ]);

                foreach ($line['items'] as $item) {
                    $record->items()->create([
                        'kind' => $item['kind'],
                        'label' => $item['label'],
                        'quantity' => $item['quantity'] === null ? null : number_format((float) $item['quantity'], 2, '.', ''),
                        'rate' => $item['rate'] === null ? null : Money::toDecimal((int) $item['rate']),
                        'amount' => Money::toDecimal((int) $item['amount']),
                        'ref' => $item['ref'],
                    ]);
                }

                $grossTotal += $line['gross_pay'];
                $employeeStatutoryTotal += $line['sss_employee'] + $line['philhealth_employee'] + $line['pagibig_employee'];
                $taxTotal += $line['tax'];
                $netTotal += $line['net_pay'];
                $employerStatutoryTotal += $line['sss_employer'] + $line['ec_employer']
                    + $line['philhealth_employer'] + $line['pagibig_employer'];
            }

            $run->forceFill([
                'computed_at' => now(),
                'gross_total' => Money::toDecimal($grossTotal),
                'employee_statutory_total' => Money::toDecimal($employeeStatutoryTotal),
                'tax_total' => Money::toDecimal($taxTotal),
                'net_total' => Money::toDecimal($netTotal),
                'employer_statutory_total' => Money::toDecimal($employerStatutoryTotal),
            ])->save();

            return $run->fresh(['lines.items', 'lines.user', 'approvals.user', 'preparedBy', 'releasedBy']);
        });
    }

    public function submit(Gym $gym, PayrollRun $run, User $actor): PayrollRun
    {
        app(CurrentGym::class)->set($gym);

        return DB::transaction(function () use ($gym, $run, $actor) {
            $run = $this->lockedRun($gym, $run);
            abort_unless($run->status === PayrollStatus::Draft, 409, 'Only a draft payroll run can be submitted.');

            $lines = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->get();
            abort_if($lines->isEmpty(), 409, 'Compute the payroll run before submitting it.');

            if ($this->hasBlockingWarnings($lines)) {
                abort(409, 'Fix the blocking warnings before submitting this payroll run.');
            }

            $steps = $this->chainFor($gym);

            $run->forceFill([
                'status' => PayrollStatus::InReview,
                'steps' => $steps,
                'current_step' => 0,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->notifyStep($gym, $run, $steps[0], $run->round);

            return $run->fresh(['lines.items', 'lines.user', 'approvals.user', 'preparedBy', 'releasedBy']);
        });
    }

    /** @param  array{decision: string, reason?: ?string}  $data */
    public function decide(Gym $gym, PayrollRun $run, User $actor, array $data): PayrollRun
    {
        app(CurrentGym::class)->set($gym);

        return DB::transaction(function () use ($gym, $run, $actor, $data) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $run = $this->lockedRun($gym, $run);

            abort_unless($run->status === PayrollStatus::InReview, 409, 'Only a payroll run in review can be decided.');

            $steps = $run->steps ?: $this->chainFor($gym);
            $index = (int) $run->current_step;
            $step = $steps[$index] ?? null;

            abort_if($step === null, 409, 'The payroll run has no current approval step.');

            $decision = $data['decision'];
            $reason = $data['reason'] ?? null;

            if ($decision === 'reject' && ! filled($reason)) {
                throw ValidationException::withMessages(['reason' => 'Say why this payroll run is being returned.']);
            }

            if (! in_array($decision, ['approve', 'reject'], true)) {
                throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
            }

            $final = $index === count($steps) - 1;
            $owner = $actor->hasGymRole($gym, GymPersonType::Owner);
            // The owner may release the final step even when they prepared the
            // run. Earlier steps still need a different approver, so nothing is
            // skipped; the release is flagged as self-released.
            $selfRelease = $decision === 'approve'
                && $final
                && $owner
                && (int) $run->prepared_by === $actor->id;

            abort_if(
                (int) $run->prepared_by === $actor->id && ! $selfRelease,
                403,
                'The preparer cannot approve their own payroll run.',
            );

            abort_if(
                PayrollApproval::where('payroll_run_id', $run->id)
                    ->where('round', $run->round)
                    ->where('user_id', $actor->id)
                    ->exists(),
                403,
                'A person cannot act on two steps of the same payroll round.',
            );

            if ($final) {
                abort_unless($owner, 403, 'Only the owner can decide the last payroll step.');
            }

            $run->approvals()->create([
                'round' => $run->round,
                'step_index' => $index,
                'step_key' => $step,
                'user_id' => $actor->id,
                'decision' => $decision,
                'reason' => $reason,
                'decided_at' => now(),
            ]);

            if ($decision === 'reject') {
                $run->forceFill([
                    'status' => PayrollStatus::Draft,
                    'current_step' => 0,
                    'round' => $run->round + 1,
                    'rejection_reason' => $reason,
                ])->save();

                if ($run->prepared_by !== null) {
                    User::find($run->prepared_by)?->notify(new PayrollDecided($gym, $run, 'rejected', $reason));
                }

                return $run->fresh(['lines.items', 'lines.user', 'approvals.user', 'preparedBy', 'releasedBy']);
            }

            if (! $final) {
                $run->forceFill(['current_step' => $index + 1])->save();
                $this->notifyStep($gym, $run, $steps[$index + 1], $run->round);

                return $run->fresh(['lines.items', 'lines.user', 'approvals.user', 'preparedBy', 'releasedBy']);
            }

            $run->forceFill([
                'status' => PayrollStatus::Released,
                'released_by' => $actor->id,
                'released_at' => now(),
                'self_released' => $selfRelease,
            ])->save();

            $this->release($gym, $run, $actor);

            return $run->fresh(['lines.items', 'lines.user', 'approvals.user', 'preparedBy', 'releasedBy']);
        });
    }

    /** The gym's chain, validated whenever it is used. */
    public function chainFor(Gym $gym): array
    {
        $steps = array_values($gym->payrollSteps());
        $known = PayrollStep::keys();

        abort_if(count($steps) < 1 || count($steps) > 3, 422, 'A payroll chain needs one to three steps.');
        abort_if(count(array_unique($steps)) !== count($steps), 422, 'A payroll chain cannot repeat a step.');
        abort_if(array_diff($steps, $known) !== [], 422, 'A payroll chain contains an unknown step.');
        abort_unless(end($steps) === PayrollStep::Owner->value, 422, 'The owner must be the last payroll step.');
        abort_if(in_array(PayrollStep::Owner->value, array_slice($steps, 0, -1), true), 422, 'The owner can only be the last payroll step.');

        return $steps;
    }

    /** @param  Collection<int, PayrollLine>  $lines */
    private function hasBlockingWarnings($lines): bool
    {
        foreach ($lines as $line) {
            foreach (($line->warnings ?? []) as $warning) {
                if (($warning['blocking'] ?? false) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    private function notifyStep(Gym $gym, PayrollRun $run, string $step, int $round): void
    {
        $stepEnum = PayrollStep::from($step);

        $approvers = $stepEnum->isOwner()
            ? $gym->people()->wherePivot('role', GymPersonType::Owner->value)->get()
            : $gym->peopleWithPermission('payroll.runs.approve');

        $acted = PayrollApproval::where('payroll_run_id', $run->id)
            ->where('round', $round)
            ->pluck('user_id')
            ->filter()
            ->all();

        $approvers->unique('id')
            ->reject(fn (User $user) => (int) $run->prepared_by === $user->id || in_array($user->id, $acted, true))
            ->each(fn (User $user) => $user->notify(new PayrollSubmitted($gym, $run, $stepEnum->label())));
    }

    private function release(Gym $gym, PayrollRun $run, User $actor): void
    {
        $this->postExpenseOnce($gym, $run, 'payroll', (string) $run->gross_total, $actor);
        $this->postExpenseOnce($gym, $run, 'statutory_contributions', (string) $run->employer_statutory_total, $actor);

        PayrollLine::withoutGlobalScope('gym')
            ->where('payroll_run_id', $run->id)
            ->get()
            ->each(fn (PayrollLine $line) => User::find($line->user_id)?->notify(new PayslipReleased($gym, $run)));

        if ($run->prepared_by !== null && (int) $run->prepared_by !== $actor->id) {
            User::find($run->prepared_by)?->notify(new PayrollDecided($gym, $run, 'released'));
        }
    }

    private function postExpenseOnce(Gym $gym, PayrollRun $run, string $category, string $amount, User $actor): void
    {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            return;
        }

        $exists = Expense::withoutGlobalScope('gym')
            ->where('source_type', 'payroll_run')
            ->where('source_id', $run->id)
            ->where('category', $category)
            ->exists();

        if ($exists) {
            return;
        }

        app(Expenses::class)->post(
            $gym,
            $category,
            $amount,
            Carbon::parse($run->ends_on->toDateString()),
            'payroll_run',
            $run->id,
            $actor,
        );
    }

    private function lockedRun(Gym $gym, PayrollRun $run): PayrollRun
    {
        return PayrollRun::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->lockForUpdate()
            ->findOrFail($run->id);
    }

    private function moneyOrNull(?int $cents): ?string
    {
        return $cents === null ? null : Money::toDecimal($cents);
    }
}
