<?php

namespace App\Http\Controllers\Gym;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Concerns\PresentsBookings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\GymService;
use App\Models\LeaveRequest;
use App\Models\Room;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\BookingDesk;
use App\Support\SessionScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-44: private slot selection uses the same availability checks as staff scheduling. */
class CoachCalendarController extends Controller
{
    use PresentsBookings, PresentsHr;

    public function calendar(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->ensureCoach($gym, $user);
        abort_unless($request->user()->is($user)
            || $request->user()->hasPermission($gym, 'training.sessions.view', 'hr.staff.view'), 403);
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : now()->startOfWeek()->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : $from->copy()->addDays(6);
        abort_if($to->lt($from) || $from->diffInDays($to) > 93, 422, 'Choose a range of at most 93 days.');
        $hours = WorkSchedule::where('user_id', $user->id)->get()->keyBy('weekday');
        $leave = LeaveRequest::where('user_id', $user->id)->where('status', LeaveRequestStatus::Approved)
            ->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)->get();
        $holidays = collect($this->holidaysFor($gym, $from->year));
        if ($to->year !== $from->year) {
            $holidays = $holidays->merge($this->holidaysFor($gym, $to->year));
        }
        $sessions = TrainingSession::with(['coach', 'room'])->where('coach_id', $user->id)
            ->where('starts_at', '>=', $from)->where('starts_at', '<', $to->copy()->addDay())->orderBy('starts_at')->get();
        $days = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            $schedule = $hours->get(strtolower($day->format('D')));
            $days[] = [
                'date' => $date, 'start_time' => $schedule?->start_time ? substr($schedule->start_time, 0, 5) : null,
                'end_time' => $schedule?->end_time ? substr($schedule->end_time, 0, 5) : null,
                'is_day_off' => ! $schedule?->start_time || ! $schedule?->end_time,
                'approved_leave' => $leave->contains(fn ($l) => $l->starts_on->toDateString() <= $date && $l->ends_on->toDateString() >= $date),
                'holidays' => $holidays->where('date', $date)->values(),
                'sessions' => $sessions->filter(fn ($s) => $s->starts_at->toDateString() === $date)->map(fn ($s) => $this->sessionSummary($s))->values(),
            ];
        }

        return response()->json(['days' => $days]);
    }

    public function slots(Request $request, Gym $gym, User $user, SessionScheduler $scheduler): JsonResponse
    {
        $this->ensureCoach($gym, $user);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'service_id' => ['required', 'integer', Rule::exists('gym_services', 'id')->where('gym_id', $gym->id)->where('activity_type', 'private_coaching')],
        ]);
        $service = GymService::findOrFail($data['service_id']);

        return response()->json(['slots' => $this->openSlots($gym, $user, $service, Carbon::parse($data['date']), $scheduler)]);
    }

    public function bookPrivate(Request $request, Gym $gym, BookingDesk $desk, SessionScheduler $scheduler): JsonResponse
    {
        $data = $request->validate([
            'gym_service_id' => ['required', 'integer', Rule::exists('gym_services', 'id')->where('gym_id', $gym->id)->where('activity_type', 'private_coaching')],
            'coach_id' => ['required', 'integer', Rule::in($this->coaches($gym)->pluck('id')->all())],
            'starts_at' => ['required', 'date'],
        ]);
        [$session, $booking] = DB::transaction(function () use ($request, $gym, $desk, $scheduler, $data) {
            // R-43, R-48: same gym lock as staff saves, then BookingDesk's occurrence lock.
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $coach = User::findOrFail($data['coach_id']);
            $service = GymService::findOrFail($data['gym_service_id']);
            $start = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone'));
            $slot = collect($this->openSlots($gym, $coach, $service, $start->copy()->startOfDay(), $scheduler))
                ->firstWhere('starts_at', $start->format('Y-m-d H:i:s'));
            if (! $slot) {
                throw ValidationException::withMessages(['starts_at' => 'This slot is no longer available. Choose another time.']);
            }
            $end = Carbon::parse($slot['ends_at']);
            $room = $this->freeRoom($gym, $coach, $start, $end, $scheduler);
            $session = TrainingSession::create([
                'gym_service_id' => $service->id, 'type' => 'private_coaching', 'name' => $service->name,
                'discipline' => $service->discipline, 'level' => $service->level,
                'coach_id' => $coach->id, 'room_id' => $room->id, 'starts_at' => $start, 'ends_at' => $end,
                'capacity' => 1, 'fee' => $service->price_included ? 0 : ($service->price ?? 0),
                'content' => $service->overview, 'bring' => $service->bring, 'created_by' => $request->user()->id,
            ])->refresh();

            return [$session, $desk->book($request->user(), $session)];
        });

        return response()->json([
            'session' => $this->timetablePayload($session, $gym, $request->user(), true),
            'booking' => $this->bookingPayload($booking, $request->user()),
        ], 201);
    }

    private function ensureCoach(Gym $gym, User $user): void
    {
        abort_unless($this->coaches($gym)->contains('id', $user->id), 404);
    }

    private function openSlots(Gym $gym, User $coach, GymService $service, Carbon $date, SessionScheduler $scheduler): array
    {
        $hours = WorkSchedule::where('user_id', $coach->id)->where('weekday', strtolower($date->format('D')))->first();
        if (! $hours?->start_time || ! $hours?->end_time || $date->lt(today())) {
            return [];
        }
        $start = Carbon::parse($date->toDateString().' '.$hours->start_time);
        $last = Carbon::parse($date->toDateString().' '.$hours->end_time);
        $slots = [];
        for (; $start->copy()->addMinutes($service->duration_minutes ?: 60)->lte($last); $start->addMinutes(30)) {
            $end = $start->copy()->addMinutes($service->duration_minutes ?: 60);
            if ($start->gt(now()) && $this->freeRoom($gym, $coach, $start, $end, $scheduler)) {
                $slots[] = ['starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s')];
            }
        }

        return $slots;
    }

    private function freeRoom(Gym $gym, User $coach, Carbon $start, Carbon $end, SessionScheduler $scheduler): ?Room
    {
        foreach ($gym->rooms()->active()->orderBy('id')->get() as $room) {
            if ($scheduler->problems($coach->id, $room->id, $start, $end) === []) {
                return $room;
            }
        }

        return null;
    }
}
