<?php

namespace App\Http\Controllers\Gym\Concerns;

use App\Enums\GymPersonType;
use App\Enums\LeaveRequestStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\NationalHolidays;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/** Shared response shapes for the HR screens (R-22 to R-27). */
trait PresentsHr
{
    /** R-42, R-44: scheduling and athlete slot selection share the same coach list. */
    protected function coaches(Gym $gym): Collection
    {
        return $gym->people()->wherePivotIn('role', [GymPersonType::Employee->value, GymPersonType::Owner->value])
            ->orderBy('first_name')->orderBy('last_name')->get()->unique('id')
            ->filter(fn (User $user) => $this->profileFor($gym, $user)?->isCoach() ?? false);
    }

    /** One employee as the staff list and employee record show them. */
    protected function employeePayload(Gym $gym, User $user, bool $canViewCompensation): array
    {
        $profile = $this->profileFor($gym, $user);
        $roles = $user->gymRoles($gym)->orderBy('name')->get();

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'photo_url' => $user->photo_path ? Storage::disk('public')->url($user->photo_path) : null,
            'is_coach' => $profile?->isCoach() ?? false,
            'roles' => $roles->pluck('name')->values()->all(),
            'profile' => $this->profilePayload($profile, $canViewCompensation),
            'schedule' => $this->scheduleRows($gym, $user),
        ];
    }

    protected function profilePayload(?EmployeeProfile $profile, bool $canViewCompensation): ?array
    {
        if (! $profile) {
            return null;
        }

        $data = [
            'job_title' => $profile->job_title,
            'started_on' => $profile->started_on?->toDateString(),
            'ended_on' => $profile->ended_on?->toDateString(),
            'employment_type' => $profile->employment_type,
            'pay_basis' => $profile->pay_basis?->value,
            'coach_photo_url' => $profile->coach_photo_path
                ? Storage::disk('public')->url($profile->coach_photo_path)
                : null,
            'coach_experience' => $profile->coach_experience,
            'coach_rank' => $profile->coach_rank,
            'coach_specialisation' => $profile->coach_specialisation,
            'coach_bio' => $profile->coach_bio,
            'coach_public' => $profile->coach_public,
        ];

        // R-23: pay details are omitted entirely when the viewer may not see them.
        if ($canViewCompensation) {
            $data['monthly_rate'] = $profile->monthly_rate;
            $data['per_session_rate'] = $profile->per_session_rate;
        }

        return $data;
    }

    protected function profileFor(Gym $gym, User $user): ?EmployeeProfile
    {
        return $gym->employees()->where('user_id', $user->id)->first();
    }

    /** The employment record to edit, created on first use. */
    protected function editableProfile(Gym $gym, User $user): EmployeeProfile
    {
        $profile = $this->profileFor($gym, $user);

        if ($profile) {
            return $profile;
        }

        $profile = new EmployeeProfile;
        $profile->user_id = $user->id;

        return $profile;
    }

    protected function scheduleRows(Gym $gym, User $user): array
    {
        $order = array_flip(array_keys(config('hr.weekdays')));

        return $gym->workSchedules()
            ->where('user_id', $user->id)
            ->get()
            ->sortBy(fn ($row) => $order[$row->weekday] ?? 99)
            ->values()
            ->map(fn ($row) => [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'weekday' => $row->weekday,
                'start_time' => $this->timeValue($row->start_time),
                'end_time' => $this->timeValue($row->end_time),
            ])
            ->all();
    }

    /** The seven-day schedule with explicit days off (R-24). */
    protected function scheduleWeek(Gym $gym, User $user): array
    {
        $rows = $gym->workSchedules()->where('user_id', $user->id)->get()->keyBy('weekday');
        $week = [];

        foreach (config('hr.weekdays') as $weekday => $label) {
            $row = $rows->get($weekday);
            $start = $row ? $this->timeValue($row->start_time) : null;
            $end = $row ? $this->timeValue($row->end_time) : null;

            $week[] = [
                'weekday' => $weekday,
                'label' => $label,
                'start_time' => $start,
                'end_time' => $end,
                'is_day_off' => $start === null || $end === null,
            ];
        }

        return $week;
    }

    /** Every leave type for one person and one year, with used and remaining days (R-26). */
    protected function leaveBalanceTypes(Gym $gym, User $user, int $year): array
    {
        $workingDays = app(WorkingDays::class);
        $overrides = $gym->leaveBalances()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->pluck('allowance', 'type');

        $approved = $gym->leaveRequests()
            ->where('user_id', $user->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->get();

        $types = [];

        foreach (config('hr.leave_types') as $type => $meta) {
            $used = 0;
            foreach ($approved as $request) {
                if ($request->type !== $type) {
                    continue;
                }

                $used += $workingDays->countInYear(
                    $gym, $user, $request->starts_on->copy(), $request->ends_on->copy(), $year,
                );
            }

            $allowance = (int) ($overrides[$type] ?? $meta['days']);

            $types[] = [
                'type' => $type,
                'label' => $meta['label'],
                'allowance' => $allowance,
                'used' => $used,
                'remaining' => $allowance - $used,
                'paid' => (bool) $meta['paid'],
            ];
        }

        return $types;
    }

    /** Approved leave as individual dates inside one year. */
    protected function approvedLeaveDates(Gym $gym, User $user, int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->startOfDay();
        $dates = [];

        $requests = $gym->leaveRequests()
            ->where('user_id', $user->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->get();

        foreach ($requests as $request) {
            $day = $request->starts_on->copy()->startOfDay();
            $last = $request->ends_on->copy()->startOfDay();

            if ($day->lessThan($start)) {
                $day = $start->copy();
            }

            if ($last->greaterThan($end)) {
                $last = $end->copy();
            }

            while ($day->lessThanOrEqualTo($last)) {
                $dates[$day->toDateString()] = true;
                $day->addDay();
            }
        }

        $dates = array_keys($dates);
        sort($dates);

        return $dates;
    }

    /** National and gym holidays for one year, without writing anything (R-27). */
    protected function holidaysFor(Gym $gym, int $year): array
    {
        $holidays = collect(NationalHolidays::forYear($year))
            ->map(fn (array $holiday) => [
                'date' => $holiday['date'],
                'name' => $holiday['name'],
                'national' => true,
            ]);

        $gymHolidays = $gym->holidays()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->map(fn ($holiday) => [
                'id' => $holiday->id,
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
                'national' => false,
            ]);

        return $holidays->merge($gymHolidays)->sortBy('date')->values()->all();
    }

    protected function leavePayload(Gym $gym, LeaveRequest $request): array
    {
        $user = $request->user;
        $decidedBy = $request->decidedBy;
        $name = $user ? trim("{$user->first_name} {$user->last_name}") : null;

        return [
            'id' => $request->id,
            'user_id' => $request->user_id,
            'user_name' => $name,
            'user' => $user ? [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'photo_url' => $user->photo_path ? Storage::disk('public')->url($user->photo_path) : null,
            ] : null,
            'type' => $request->type,
            'type_label' => config("hr.leave_types.{$request->type}.label", $request->type),
            'starts_on' => $request->starts_on->toDateString(),
            'ends_on' => $request->ends_on->toDateString(),
            'working_days' => $request->working_days,
            'reason' => $request->reason,
            'status' => $request->status->value,
            'decided_by' => $decidedBy?->id,
            'decided_by_name' => $decidedBy ? trim("{$decidedBy->first_name} {$decidedBy->last_name}") : null,
            'decided_at' => $request->decided_at?->toIso8601String(),
            'decision_note' => $request->decision_note,
            'attachment_name' => $request->attachment_name,
            'attachment_mime' => $request->attachment_mime,
            'attachment_size' => $request->attachment_size,
            'over_balance' => $this->overBalance($gym, $request),
        ];
    }

    /** R-26: a request may be larger than the remaining days; it is flagged, never blocked. */
    protected function overBalance(Gym $gym, LeaveRequest $request): bool
    {
        $year = $request->starts_on->year;
        $remaining = $this->allowanceFor($gym, $request, $year)
            - $this->approvedUsed($gym, $request, $year, $request->id);

        return $request->working_days > max(0, $remaining);
    }

    protected function allowanceFor(Gym $gym, LeaveRequest $request, int $year): int
    {
        $allowance = $gym->leaveBalances()
            ->where('user_id', $request->user_id)
            ->where('type', $request->type)
            ->where('year', $year)
            ->value('allowance');

        return (int) ($allowance ?? config("hr.leave_types.{$request->type}.days", 0));
    }

    protected function approvedUsed(Gym $gym, LeaveRequest $request, int $year, ?int $excludeId = null): int
    {
        $query = $gym->leaveRequests()
            ->where('user_id', $request->user_id)
            ->where('type', $request->type)
            ->where('status', LeaveRequestStatus::Approved->value);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $workingDays = app(WorkingDays::class);
        $employee = User::find($request->user_id);
        $used = 0;

        foreach ($query->get() as $approved) {
            $used += $workingDays->countInYear(
                $gym, $employee, $approved->starts_on->copy(), $approved->ends_on->copy(), $year,
            );
        }

        return $used;
    }

    /** @return Collection<int, User> */
    protected function leaveApprovers(Gym $gym): Collection
    {
        return $gym->peopleWithPermission('hr.leave.manage');
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
