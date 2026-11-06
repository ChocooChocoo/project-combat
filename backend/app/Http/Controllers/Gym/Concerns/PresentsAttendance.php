<?php

namespace App\Http\Controllers\Gym\Concerns;

use App\Models\OvertimeRequest;
use App\Models\PerformanceNote;
use App\Models\StaffAttendance;

/** Shared response shapes for staff attendance, overtime and performance notes (R-56 to R-58). */
trait PresentsAttendance
{
    protected function attendancePayload(StaffAttendance $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'user_name' => $this->personName($row->user),
            'work_date' => $row->work_date?->toDateString(),
            'scheduled_start' => $this->timeLabel($row->scheduled_start),
            'scheduled_end' => $this->timeLabel($row->scheduled_end),
            'clock_in_time' => $row->clock_in_at?->format('H:i'),
            'clock_out_time' => $row->clock_out_at?->format('H:i'),
            'clock_in_at' => $row->clock_in_at?->format('Y-m-d H:i:s'),
            'clock_out_at' => $row->clock_out_at?->format('Y-m-d H:i:s'),
            'status' => $row->status->value,
            'late_minutes' => $row->late_minutes,
            'undertime_minutes' => $row->undertime_minutes,
            'worked_minutes' => $row->worked_minutes,
            'missing_clock_out' => $row->missing_clock_out,
            'source' => $row->source->value,
            'explanation' => $row->explanation,
            'recorded_by_name' => $this->personName($row->recordedBy),
            'changes_count' => $row->changes_count ?? null,
        ];
    }

    protected function overtimePayload(OvertimeRequest $request): array
    {
        return [
            'id' => $request->id,
            'user_id' => $request->user_id,
            'user_name' => $this->personName($request->user),
            'work_date' => $request->work_date?->toDateString(),
            'kind' => $request->kind->value,
            'requested_minutes' => $request->requested_minutes,
            'approved_minutes' => $request->approved_minutes,
            'reason' => $request->reason,
            'status' => $request->status->value,
            'decided_by_name' => $this->personName($request->decidedBy),
            'decided_at' => $request->decided_at?->format('Y-m-d H:i'),
            'decision_note' => $request->decision_note,
        ];
    }

    protected function performancePayload(PerformanceNote $note): array
    {
        return [
            'id' => $note->id,
            'user_id' => $note->user_id,
            'author_id' => $note->author_id,
            'author_name' => $this->personName($note->author),
            'noted_on' => $note->noted_on?->toDateString(),
            'kind' => $note->kind->value,
            'body' => $note->body,
            'created_at' => $note->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function personName($user): ?string
    {
        return $user ? trim("{$user->first_name} {$user->last_name}") : null;
    }

    private function timeLabel(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
