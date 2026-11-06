<?php

namespace App\Support;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\DayKind;
use App\Enums\OvertimeKind;
use App\Enums\OvertimeStatus;
use App\Models\Gym;
use App\Models\OvertimeRequest;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\OvertimeRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * R-56 and R-57: the only place a staff attendance row is written.
 * Late minutes count from the scheduled start once the grace has passed.
 */
class StaffAttendanceBook
{
    /** @return array{start: ?string, end: ?string} */
    public function scheduleFor(Gym $gym, User $employee, Carbon $date): array
    {
        $weekday = strtolower($date->format('D'));

        $row = WorkSchedule::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->where('user_id', $employee->id)
            ->where('weekday', $weekday)
            ->first();

        if ($row?->start_time && $row?->end_time) {
            return ['start' => substr($row->start_time, 0, 5), 'end' => substr($row->end_time, 0, 5)];
        }

        return ['start' => null, 'end' => null];
    }

    public function clockIn(Gym $gym, User $employee): StaffAttendance
    {
        $date = today();
        $this->assertNoRow($gym, $employee, $date);

        $schedule = $this->scheduleFor($gym, $employee, $date);
        $in = now();
        $derived = $this->derive($date, $schedule['start'], $schedule['end'], $in, null, (int) $gym->staff_grace_minutes);

        return $gym->staffAttendance()->create([
            'user_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'scheduled_start' => $schedule['start'],
            'scheduled_end' => $schedule['end'],
            'clock_in_at' => $in,
            'status' => $derived['status']->value,
            'late_minutes' => $derived['late_minutes'],
            'worked_minutes' => 0,
            'source' => AttendanceSource::Self->value,
        ]);
    }

    public function clockOut(Gym $gym, User $employee): StaffAttendance
    {
        return DB::transaction(function () use ($gym, $employee) {
            $row = $gym->staffAttendance()
                ->where('user_id', $employee->id)
                ->whereDate('work_date', today()->toDateString())
                ->lockForUpdate()
                ->first();

            abort_unless($row, 404, 'Clock in before you clock out.');
            abort_if($row->clock_out_at, 409, 'You already clocked out today.');

            $out = now();
            $derived = $this->derive($row->work_date, $this->label($row->scheduled_start), $this->label($row->scheduled_end), $row->clock_in_at, $out, (int) $gym->staff_grace_minutes);

            $row->update([
                'clock_out_at' => $out,
                'status' => $derived['status']->value,
                'late_minutes' => $derived['late_minutes'],
                'undertime_minutes' => $derived['undertime_minutes'],
                'worked_minutes' => $derived['worked_minutes'],
                'missing_clock_out' => false,
            ]);

            $this->syncOvertime($gym, $row->fresh());

            return $row->fresh();
        });
    }

    /** @param array{work_date: string, clock_in_time?: ?string, clock_out_time?: ?string, note?: ?string} $data */
    public function deskEntry(Gym $gym, User $employee, array $data, User $actor): StaffAttendance
    {
        $date = Carbon::parse($data['work_date'])->startOfDay();
        PayrollLock::assertOpen($gym, $date);
        $this->assertNoRow($gym, $employee, $date);

        $schedule = $this->scheduleFor($gym, $employee, $date);
        $in = $this->at($date, $data['clock_in_time'] ?? null);
        $out = $this->at($date, $data['clock_out_time'] ?? null);
        abort_if($in && $out && $out->lessThan($in), 422, 'Clock-out cannot be before clock-in.');

        $derived = $this->derive($date, $schedule['start'], $schedule['end'], $in, $out, (int) $gym->staff_grace_minutes);

        $row = $gym->staffAttendance()->create([
            'user_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'scheduled_start' => $schedule['start'],
            'scheduled_end' => $schedule['end'],
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'status' => $derived['status']->value,
            'late_minutes' => $derived['late_minutes'],
            'undertime_minutes' => $derived['undertime_minutes'],
            'worked_minutes' => $derived['worked_minutes'],
            'source' => AttendanceSource::Desk->value,
            'explanation' => $data['note'] ?? null,
            'recorded_by' => $actor->id,
        ]);

        if ($out) {
            $this->syncOvertime($gym, $row);
        }

        return $row->fresh();
    }

    /** @param array{clock_in_time?: ?string, clock_out_time?: ?string, scheduled_start?: ?string, scheduled_end?: ?string} $data */
    public function correct(Gym $gym, StaffAttendance $entry, array $data, User $actor, string $reason): StaffAttendance
    {
        return DB::transaction(function () use ($gym, $entry, $data, $actor, $reason) {
            PayrollLock::assertOpen($gym, $entry->work_date);
            $entry = $gym->staffAttendance()->lockForUpdate()->findOrFail($entry->id);
            $before = $this->snapshot($entry);

            $start = $data['scheduled_start'] ?? $this->label($entry->scheduled_start);
            $end = $data['scheduled_end'] ?? $this->label($entry->scheduled_end);
            $in = array_key_exists('clock_in_time', $data) ? $this->at($entry->work_date, $data['clock_in_time']) : $entry->clock_in_at;
            $out = array_key_exists('clock_out_time', $data) ? $this->at($entry->work_date, $data['clock_out_time']) : $entry->clock_out_at;

            abort_if($in && $out && $out->lessThan($in), 422, 'Clock-out cannot be before clock-in.');

            $derived = $this->derive($entry->work_date, $start, $end, $in, $out, (int) $gym->staff_grace_minutes);

            $entry->update([
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'clock_in_at' => $in,
                'clock_out_at' => $out,
                'status' => $derived['status']->value,
                'late_minutes' => $derived['late_minutes'],
                'undertime_minutes' => $derived['undertime_minutes'],
                'worked_minutes' => $derived['worked_minutes'],
                'missing_clock_out' => false,
            ]);

            $entry->changes()->create([
                'changed_by' => $actor->id,
                'before' => $before,
                'after' => $this->snapshot($entry->fresh()),
                'reason' => $reason,
            ]);

            $this->resetOvertime($gym, $entry->fresh());

            return $entry->fresh();
        });
    }

    public function explain(User $employee, StaffAttendance $entry, string $text): StaffAttendance
    {
        abort_unless($entry->user_id === $employee->id, 403, 'You can only explain your own attendance.');
        abort_unless(in_array($entry->status, [AttendanceStatus::Absent, AttendanceStatus::Late], true), 409, 'Only an absence or a late record can be explained.');

        $entry->update(['explanation' => $text]);

        return $entry->fresh();
    }

    /** @return array{status: AttendanceStatus, late_minutes: int, undertime_minutes: int, worked_minutes: int} */
    private function derive(Carbon $date, ?string $start, ?string $end, ?Carbon $in, ?Carbon $out, int $grace): array
    {
        $status = AttendanceStatus::Absent;
        $late = 0;
        $undertime = 0;
        $worked = 0;

        if ($in) {
            $status = AttendanceStatus::Present;

            if ($start) {
                $scheduledStart = $this->at($date, $start);
                if ($in->greaterThan($scheduledStart->copy()->addMinutes($grace))) {
                    $late = max(0, $this->minutes($scheduledStart, $in));
                    $status = AttendanceStatus::Late;
                }
            }
        }

        if ($in && $out) {
            $worked = max(0, $this->minutes($in, $out));

            if ($end) {
                $scheduledEnd = $this->at($date, $end);
                if ($out->lessThan($scheduledEnd)) {
                    $undertime = max(0, $this->minutes($out, $scheduledEnd));
                }
            }
        }

        return ['status' => $status, 'late_minutes' => $late, 'undertime_minutes' => $undertime, 'worked_minutes' => $worked];
    }

    private function syncOvertime(Gym $gym, StaffAttendance $row): void
    {
        $extra = $this->overtimeFor($gym, $row);

        if (! $extra['kind'] || $extra['minutes'] <= 0) {
            return;
        }

        $request = $gym->overtimeRequests()->firstOrCreate(
            [
                'user_id' => $row->user_id,
                'work_date' => $row->work_date->toDateString(),
                'staff_attendance_id' => $row->id,
                'kind' => $extra['kind']->value,
            ],
            [
                'requested_minutes' => $extra['minutes'],
                'reason' => 'Recorded automatically at clock-out.',
                'status' => OvertimeStatus::Pending->value,
            ],
        );

        if ($request->wasRecentlyCreated) {
            $name = trim("{$row->user->first_name} {$row->user->last_name}");

            foreach ($gym->peopleWithPermission('hr.overtime.approve') as $approver) {
                if ($approver->id !== $row->user_id) {
                    $approver->notify(new OvertimeRequested($gym, $request, $name));
                }
            }
        }
    }

    private function resetOvertime(Gym $gym, StaffAttendance $row): void
    {
        $extra = $this->overtimeFor($gym, $row);

        $requests = $gym->overtimeRequests()->where('staff_attendance_id', $row->id)->get();

        foreach ($requests as $request) {
            if ($request->status === OvertimeStatus::Rejected) {
                continue;
            }

            $request->update([
                'requested_minutes' => $extra['minutes'],
                'approved_minutes' => null,
                'status' => OvertimeStatus::Pending->value,
                'decided_by' => null,
                'decided_at' => null,
                'decision_note' => null,
            ]);
        }

        if ($requests->isEmpty() && $extra['kind'] && $extra['minutes'] > 0) {
            $gym->overtimeRequests()->create([
                'user_id' => $row->user_id,
                'work_date' => $row->work_date->toDateString(),
                'staff_attendance_id' => $row->id,
                'kind' => $extra['kind']->value,
                'requested_minutes' => $extra['minutes'],
                'reason' => 'Corrected attendance.',
                'status' => OvertimeStatus::Pending->value,
            ]);
        }
    }

    /** @return array{kind: ?OvertimeKind, minutes: int} */
    private function overtimeFor(Gym $gym, StaffAttendance $row): array
    {
        if (! $row->clock_in_at || ! $row->clock_out_at) {
            return ['kind' => null, 'minutes' => 0];
        }

        $end = $this->label($row->scheduled_end);

        if ($end) {
            $scheduledEnd = $this->at($row->work_date, $end);
            $minutes = $this->minutes($scheduledEnd, $row->clock_out_at);

            return $minutes >= (int) $gym->overtime_min_minutes
                ? ['kind' => OvertimeKind::AfterShift, 'minutes' => $minutes]
                : ['kind' => null, 'minutes' => 0];
        }

        $kind = app(WorkingDays::class)->kind($gym, $row->user, $row->work_date);
        $minutes = $this->minutes($row->clock_in_at, $row->clock_out_at);

        return $kind === DayKind::Rest && $minutes > 0
            ? ['kind' => OvertimeKind::RestDay, 'minutes' => $minutes]
            : ['kind' => null, 'minutes' => 0];
    }

    private function assertNoRow(Gym $gym, User $employee, Carbon $date): void
    {
        $exists = $gym->staffAttendance()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->exists();

        abort_if($exists, 409, 'Attendance for that day is already recorded.');
    }

    private function at(Carbon $date, ?string $time): ?Carbon
    {
        if (! $time) {
            return null;
        }

        return Carbon::parse($date->toDateString().' '.$time);
    }

    private function minutes(Carbon $from, Carbon $to): int
    {
        return max(0, (int) round(($to->getTimestamp() - $from->getTimestamp()) / 60));
    }

    private function label(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /** @return array<string, mixed> */
    private function snapshot(StaffAttendance $entry): array
    {
        return [
            'scheduled_start' => $this->label($entry->scheduled_start),
            'scheduled_end' => $this->label($entry->scheduled_end),
            'clock_in_time' => $entry->clock_in_at?->format('H:i'),
            'clock_out_time' => $entry->clock_out_at?->format('H:i'),
            'status' => $entry->status->value,
            'late_minutes' => $entry->late_minutes,
            'undertime_minutes' => $entry->undertime_minutes,
            'worked_minutes' => $entry->worked_minutes,
            'explanation' => $entry->explanation,
        ];
    }
}
