<?php

namespace App\Support;

use App\Enums\LeaveRequestStatus;
use App\Enums\SessionStatus;
use App\Models\GymHoliday;
use App\Models\LeaveRequest;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;

/** R-43: reusable occurrence checks, always scoped to CurrentGym. */
class SessionScheduler
{
    /** @return array<string, string[]> */
    public function problems(int $coachId, int $roomId, Carbon $start, Carbon $end, ?int $ignoreId = null): array
    {
        $errors = [];
        $date = $start->toDateString();
        $day = $start->format('D j M');
        $coach = User::findOrFail($coachId);
        $name = trim("{$coach->first_name} {$coach->last_name}");
        $overlaps = TrainingSession::with('room')->where('status', SessionStatus::Scheduled)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where(fn ($query) => $query->where('coach_id', $coachId)->orWhere('room_id', $roomId))->get();

        foreach ($overlaps as $session) {
            $time = $session->starts_at->format('g:i A').'–'.$session->ends_at->format('g:i A');
            if ($session->coach_id === $coachId) {
                $errors['coach_id'][] = "Coach {$name} already has {$session->name} {$time} on {$day}.";
            }
            if ($session->room_id === $roomId) {
                $errors['room_id'][] = "Room {$session->room->name} already has {$session->name} {$time} on {$day}.";
            }
        }

        $hours = WorkSchedule::where('user_id', $coachId)->where('weekday', strtolower($start->format('D')))->first();
        if (! $hours?->start_time || ! $hours?->end_time
            || $start->format('H:i:s') < Carbon::parse($hours->start_time)->format('H:i:s')
            || $end->format('H:i:s') > Carbon::parse($hours->end_time)->format('H:i:s')) {
            $errors['starts_at'][] = "Coach {$name} is outside working hours on {$day}.";
        }
        if (LeaveRequest::where('user_id', $coachId)->where('status', LeaveRequestStatus::Approved)
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists()) {
            $errors['starts_at'][] = "Coach {$name} is on approved leave on {$day}.";
        }
        $holiday = GymHoliday::whereDate('date', $date)->value('name')
            ?? collect(NationalHolidays::forYear($start->year))->firstWhere('date', $date)['name'] ?? null;
        if ($holiday !== null) {
            $errors['starts_at'][] = "{$day} is a holiday: {$holiday}.";
        }

        return $errors;
    }
}
