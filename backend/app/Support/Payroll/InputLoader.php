<?php

namespace App\Support\Payroll;

use App\Enums\OvertimeStatus;
use App\Enums\SessionStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\StaffAttendance;
use App\Models\StatutoryRate;
use App\Models\TrainingSession;
use App\Models\WorkSchedule;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * R-59: read everything a payroll run needs for one period. Every query names
 * the gym explicitly; nothing is taken from the screen and no data is written.
 */
class InputLoader
{
    public function __construct(private WorkingDays $workingDays) {}

    /**
     * @return array{
     *     period: array<string, mixed>,
     *     employees: list<array<string, mixed>>,
     *     inputs: array<int, array<string, mixed>>,
     *     rates: array<string, array<string, mixed>>
     * }
     */
    public function load(Gym $gym, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $frequency = $gym->payroll_frequency ?: 'semi_monthly';

        $profiles = EmployeeProfile::withoutGlobalScope('gym')
            ->with('user')
            ->where('gym_id', $gym->id)
            ->where(fn ($query) => $query->whereNull('started_on')->orWhereDate('started_on', '<=', $to))
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $from))
            ->orderBy('id')
            ->get()
            ->filter(fn (EmployeeProfile $profile) => $profile->user !== null)
            ->values();

        $userIds = $profiles->pluck('user_id')->all();

        $schedules = WorkSchedule::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $attendance = StaffAttendance::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereIn('user_id', $userIds)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->get()
            ->keyBy(fn (StaffAttendance $row) => $row->user_id.'|'.$row->work_date->toDateString());

        $leaveRequests = LeaveRequest::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->get()
            ->groupBy('user_id');

        $overtime = OvertimeRequest::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereIn('user_id', $userIds)
            ->where('status', OvertimeStatus::Approved->value)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->orderBy('work_date')
            ->get()
            ->groupBy('user_id');

        $sessions = TrainingSession::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereIn('coach_id', $userIds)
            ->whereBetween('ends_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['bookings' => fn ($query) => $query->withoutGlobalScope('gym')])
            ->orderBy('ends_at')
            ->get()
            ->groupBy('coach_id');

        $rates = (new Statutory)->selectRates(
            StatutoryRate::query()
                ->forGym($gym)
                ->whereDate('effective_from', '<=', $to)
                ->orderBy('effective_from')
                ->orderBy('id')
                ->get(),
            $to,
        );

        $employees = [];
        $inputs = [];

        foreach ($profiles as $profile) {
            $user = $profile->user;
            $rows = $schedules->get($user->id, collect());
            $weekdays = $this->workingWeekdays($gym, $rows);
            $shiftMinutes = $this->shiftMinutes($rows);
            $startedOn = $profile->started_on;
            $endedOn = $profile->ended_on;
            $fullPeriod = ($startedOn === null || $startedOn->lessThanOrEqualTo($from))
                && ($endedOn === null || $endedOn->greaterThanOrEqualTo($to));

            $employees[] = [
                'user_id' => $user->id,
                'name' => trim("{$user->first_name} {$user->last_name}"),
                'job_title' => $profile->job_title,
                'pay_basis' => $profile->pay_basis?->value,
                'employment_type' => $profile->employment_type ?: 'employee',
                'monthly_rate_cents' => $profile->monthly_rate !== null ? Money::toCents($profile->monthly_rate) : null,
                'per_session_rate_cents' => $profile->per_session_rate !== null ? Money::toCents($profile->per_session_rate) : null,
                'started_on' => $startedOn?->toDateString(),
                'ended_on' => $endedOn?->toDateString(),
                'full_period' => $fullPeriod,
                'working_weekdays' => $weekdays,
                'annual_factor' => $this->annualFactor($weekdays, $from->year),
                'shift_minutes' => $shiftMinutes,
            ];

            $leaveDates = $this->leaveDates($leaveRequests->get($user->id, collect()), $from, $to);
            $days = [];

            for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
                $inEmployment = ($startedOn === null || $day->greaterThanOrEqualTo($startedOn))
                    && ($endedOn === null || $day->lessThanOrEqualTo($endedOn));
                $kind = $this->workingDays->kind($gym, $user, $day)->value;
                $row = $attendance->get($user->id.'|'.$day->toDateString());

                $days[] = [
                    'date' => $day->toDateString(),
                    'kind' => $kind,
                    'in_employment' => $inEmployment,
                    'future' => $day->greaterThan(now()->startOfDay()),
                    'attendance' => $row?->status?->value,
                    'late_minutes' => (int) ($row?->late_minutes ?? 0),
                    'undertime_minutes' => (int) ($row?->undertime_minutes ?? 0),
                    'missing_clock_out' => (bool) ($row?->missing_clock_out ?? false),
                    'leave' => $kind === 'working' && $inEmployment ? ($leaveDates[$day->toDateString()] ?? null) : null,
                ];
            }

            $inputs[$user->id] = [
                'days' => $days,
                'overtime' => $overtime->get($user->id, collect())
                    ->filter(fn (OvertimeRequest $request) => $this->insideEmployment($request->work_date, $startedOn, $endedOn))
                    ->map(fn (OvertimeRequest $request) => [
                        'id' => $request->id,
                        'work_date' => $request->work_date->toDateString(),
                        'approved_minutes' => (int) $request->approved_minutes,
                    ])->values()->all(),
                'sessions' => $this->sessionRows($sessions->get($user->id, collect()), $startedOn, $endedOn),
            ];
        }

        return [
            'period' => [
                'start' => $from->toDateString(),
                'end' => $to->toDateString(),
                'frequency' => $frequency,
                'days' => (int) $from->diffInDays($to) + 1,
            ],
            'employees' => $employees,
            'inputs' => $inputs,
            'rates' => $rates,
        ];
    }

    /** @return list<string> */
    private function workingWeekdays(Gym $gym, Collection $schedules): array
    {
        $weekdays = $schedules
            ->filter(fn (WorkSchedule $row) => $row->start_time !== null && $row->end_time !== null)
            ->pluck('weekday')
            ->unique()
            ->values()
            ->all();

        return $weekdays ?: $gym->workingDays();
    }

    private function shiftMinutes(Collection $schedules): int
    {
        $order = array_flip(array_keys(config('hr.weekdays')));

        $row = $schedules
            ->filter(fn (WorkSchedule $row) => $row->start_time !== null && $row->end_time !== null)
            ->sortBy(fn (WorkSchedule $row) => $order[$row->weekday] ?? 99)
            ->first();

        if (! $row) {
            return 480;
        }

        $start = Carbon::parse('2000-01-01 '.substr($row->start_time, 0, 5));
        $end = Carbon::parse('2000-01-01 '.substr($row->end_time, 0, 5));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return max(1, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60));
    }

    /** @param  list<string>  $weekdays */
    private function annualFactor(array $weekdays, int $year): int
    {
        $total = 0;

        for ($day = Carbon::create($year, 1, 1)->startOfDay(); $day->year === $year; $day->addDay()) {
            if (in_array(strtolower($day->format('D')), $weekdays, true)) {
                $total++;
            }
        }

        return max(1, $total);
    }

    /** @return array<string, string> date => paid|unpaid */
    private function leaveDates(Collection $requests, Carbon $from, Carbon $to): array
    {
        $dates = [];

        foreach ($requests as $request) {
            $paid = (bool) config("hr.leave_types.{$request->type}.paid", false);
            $day = $request->starts_on->copy()->startOfDay();
            $end = $request->ends_on->copy()->startOfDay();

            if ($day->lessThan($from)) {
                $day = $from->copy();
            }

            if ($end->greaterThan($to)) {
                $end = $to->copy();
            }

            while ($day->lessThanOrEqualTo($end)) {
                $existing = $dates[$day->toDateString()] ?? null;
                $dates[$day->toDateString()] = $existing === 'paid' || $paid ? 'paid' : 'unpaid';
                $day->addDay();
            }
        }

        return $dates;
    }

    /** @return list<array<string, mixed>> */
    private function sessionRows(Collection $sessions, ?Carbon $startedOn, ?Carbon $endedOn): array
    {
        return $sessions->map(function (TrainingSession $session) use ($startedOn, $endedOn) {
            $attended = $session->bookings
                ->contains(fn ($booking) => in_array($booking->attendance, ['present', 'late'], true));
            $reason = null;

            if (! $this->insideEmployment($session->ends_at, $startedOn, $endedOn)) {
                $reason = 'Outside employment.';
            } elseif ($session->status !== SessionStatus::Scheduled) {
                $reason = 'Session was not scheduled.';
            } elseif ($session->flagged_at !== null) {
                $reason = 'Session was flagged.';
            } elseif (! $attended) {
                $reason = 'No booking was present or late.';
            }

            return [
                'id' => $session->id,
                'name' => $session->name,
                'starts_at' => $session->starts_at->toIso8601String(),
                'ends_at' => $session->ends_at->toIso8601String(),
                'counted' => $reason === null,
                'reason' => $reason,
            ];
        })->values()->all();
    }

    private function insideEmployment(Carbon $date, ?Carbon $startedOn, ?Carbon $endedOn): bool
    {
        $day = $date->copy()->startOfDay();

        return ($startedOn === null || $day->greaterThanOrEqualTo($startedOn))
            && ($endedOn === null || $day->lessThanOrEqualTo($endedOn));
    }
}
