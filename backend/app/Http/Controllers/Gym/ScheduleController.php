<?php

namespace App\Http\Controllers\Gym;

use App\Enums\AccessLevel;
use App\Enums\GymPersonType;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-24: an employee's weekly working hours and days off. */
class ScheduleController extends Controller
{
    use PresentsHr;

    public function show(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->ensureEmployee($gym, $user);

        abort_unless(
            $request->user()->is($user) || $request->user()->canInGym($gym, Module::Hr, AccessLevel::View),
            403,
        );

        return response()->json(['schedule' => $this->scheduleWeek($gym, $user)]);
    }

    public function update(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->ensureEmployee($gym, $user);

        $data = $request->validate([
            'schedule' => ['required', 'array', 'size:7'],
            'schedule.*.weekday' => ['required', Rule::in(array_keys(config('hr.weekdays')))],
            'schedule.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedule.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $weekdays = collect($data['schedule'])->pluck('weekday');

        if ($weekdays->unique()->count() !== count(config('hr.weekdays')) || $weekdays->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'schedule' => 'Send each weekday exactly once.',
            ]);
        }

        foreach ($data['schedule'] as $index => $row) {
            $start = $row['start_time'] ?? null;
            $end = $row['end_time'] ?? null;

            if (($start === null) !== ($end === null)) {
                throw ValidationException::withMessages([
                    "schedule.{$index}.end_time" => 'Set both times or neither.',
                ]);
            }

            if ($start !== null && $end !== null && $end <= $start) {
                throw ValidationException::withMessages([
                    "schedule.{$index}.end_time" => 'The end time must be after the start time.',
                ]);
            }
        }

        DB::transaction(function () use ($gym, $user, $data) {
            $gym->workSchedules()->where('user_id', $user->id)->delete();

            foreach ($data['schedule'] as $row) {
                $gym->workSchedules()->create([
                    'user_id' => $user->id,
                    'weekday' => $row['weekday'],
                    'start_time' => $row['start_time'] ?? null,
                    'end_time' => $row['end_time'] ?? null,
                ]);
            }
        });

        return response()->json(['schedule' => $this->scheduleWeek($gym, $user)]);
    }

    private function ensureEmployee(Gym $gym, User $user): void
    {
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 404);
    }
}
