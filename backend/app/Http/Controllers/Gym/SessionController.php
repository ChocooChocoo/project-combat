<?php

namespace App\Http\Controllers\Gym;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\SessionChanged;
use App\Support\BookingDesk;
use App\Support\BookingLedger;
use App\Support\SessionFlagger;
use App\Support\SessionScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-42, R-43: one session or an atomic set of repeating occurrences. */
class SessionController extends Controller
{
    use PresentsHr;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'coach_id' => ['sometimes', 'integer'], 'room_id' => ['sometimes', 'integer'],
            'flagged' => ['sometimes', 'boolean'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : now()->startOfWeek();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : $from->copy()->addDays(6);
        $sessions = TrainingSession::with(['coach', 'room', 'service'])
            ->when(! ($data['flagged'] ?? false) || isset($data['from']) || isset($data['to']), fn ($query) => $query->where('starts_at', '>=', $from)->where('starts_at', '<', $to->copy()->addDay()))
            ->when($data['flagged'] ?? false, fn ($query) => $query->whereNotNull('flagged_at')->where('status', SessionStatus::Scheduled))
            ->when(isset($data['coach_id']), fn ($query) => $query->where('coach_id', $data['coach_id']))
            ->when(isset($data['room_id']), fn ($query) => $query->where('room_id', $data['room_id']))
            ->orderBy('starts_at')->orderBy('id')->get();

        return response()->json(['sessions' => $sessions->map(fn ($session) => $this->payload($session))]);
    }

    public function show(Gym $gym, int $session): JsonResponse
    {
        return response()->json(['session' => $this->payload(TrainingSession::findOrFail($session))]);
    }

    public function options(Gym $gym): JsonResponse
    {
        $all = config('verification.disciplines'); // R-124
        return response()->json(['options' => [
            'disciplines' => collect(array_unique([...array_intersect($gym->disciplines ?? [], array_keys($all)), ...array_keys($all)]))
                ->map(fn ($key) => ['value' => $key, 'label' => $all[$key]])->values(),
            'coaches' => $this->coaches($gym)->map(fn ($user) => ['id' => $user->id, 'name' => trim("{$user->first_name} {$user->last_name}")])->values(),
            'rooms' => $gym->rooms()->active()->orderBy('name')->get(['id', 'name']),
            'services' => $gym->gymServices()->orderBy('name')->get([
                'id', 'name', 'discipline', 'activity_type', 'overview', 'level', 'duration_minutes', 'bring', 'price', 'price_included',
            ]),
            'plans' => $gym->membershipPlans()->orderBy('name')->get(['id', 'name']),
            'activity_types' => $gym->activityTypeOptions(), 'levels' => config('offer.levels'),
            'thumbnail_max_kb' => config('offer.service_photos.max_kb'), // R-125
        ]]);
    }

    public function store(Request $request, Gym $gym, SessionScheduler $scheduler): JsonResponse
    {
        $sessions = DB::transaction(function () use ($request, $gym, $scheduler) {
            // R-43: serialize saves per gym. SQLite ignores this lock; MySQL honours it.
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $data = $this->validated($request, $gym);
            $start = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone'));
            $end = Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone'));
            $repeat = $data['repeat'] ?? null;
            unset($data['repeat']);
            $dates = [$start];
            if ($repeat !== null) {
                $until = Carbon::parse($repeat['until'])->startOfDay();
                if ($until->lt($start->copy()->startOfDay()) || $until->gt($start->copy()->startOfDay()->addWeeks(26))) {
                    throw ValidationException::withMessages(['repeat.until' => 'Repeat until must be within 26 weeks of the first session.']);
                }
                // The first occurrence always stays; selected weekdays add subsequent occurrences.
                for ($day = $start->copy()->addDay(); $day->toDateString() <= $until->toDateString(); $day->addDay()) {
                    if (in_array(strtolower($day->format('D')), $repeat['weekdays'], true)) {
                        $dates[] = $day->copy();
                    }
                }
                if (count($dates) > 100) {
                    throw ValidationException::withMessages(['repeat' => 'A repeat may create at most 100 occurrences.']);
                }
            }
            $errors = [];
            foreach ($dates as $date) {
                $finish = $date->copy()->setTimeFrom($end);
                foreach ($scheduler->problems($data['coach_id'], $data['room_id'], $date, $finish) as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors[$field][] = $date->toDateString().': '.$message;
                    }
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $series = $repeat !== null ? (string) Str::uuid() : null;

            return collect($dates)->map(fn ($date) => TrainingSession::create([
                ...$data, 'starts_at' => $date, 'ends_at' => $date->copy()->setTimeFrom($end),
                'series_id' => $series, 'created_by' => $request->user()->id,
            ])->refresh());
        });
        $payloads = $sessions->map(fn ($session) => $this->payload($session));

        return response()->json(['session' => $payloads->first(), 'sessions' => $payloads], 201);
    }

    public function update(Request $request, Gym $gym, int $session, SessionScheduler $scheduler): JsonResponse
    {
        $model = DB::transaction(function () use ($request, $gym, $session, $scheduler) {
            // R-43: same lock order as creation. SQLite ignores it; MySQL honours it.
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $model = TrainingSession::lockForUpdate()->findOrFail($session);
            abort_if($model->status === SessionStatus::Cancelled, 409, 'Cancelled sessions cannot be edited.');
            $data = $this->validated($request, $gym, true, $model->discipline);
            $start = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone'));
            $end = Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone'));
            $timeChanged = ! $start->eq($model->starts_at) || ! $end->eq($model->ends_at);
            $roomChanged = (int) $data['room_id'] !== $model->room_id;
            $moved = $timeChanged || $roomChanged;
            $coachChanged = (int) $data['coach_id'] !== $model->coach_id;
            $errors = $moved || $coachChanged ? $scheduler->problems($data['coach_id'], $data['room_id'], $start, $end, $model->id) : [];
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            if ($data['capacity'] < $model->placesTaken()) {
                throw ValidationException::withMessages(['capacity' => 'Capacity cannot be below the places already taken.']);
            }
            $oldCoach = $model->coach_id;
            $oldTime = $model->starts_at->format('D j M Y, g:i A').'–'.$model->ends_at->format('g:i A');
            $oldRoom = $model->room->name;
            $oldCapacity = $model->capacity;
            if ($moved || $coachChanged) {
                $data['flagged_at'] = null;
                $data['flag_reason'] = null;
            }
            $model->update([...$data, 'starts_at' => $start, 'ends_at' => $end]);
            if ($timeChanged) {
                $model->bookings()->update(['reminder_sent_at' => null]);
            }
            $model->refresh();
            if ($moved) {
                $changes = [];
                if ($timeChanged) {
                    $changes[] = $oldTime.' → '.$start->format('D j M Y, g:i A').'–'.$end->format('g:i A').'.';
                }
                if ($roomChanged) {
                    $changes[] = "Room {$oldRoom} → {$model->room->name}.";
                }
                $this->notifyChange($model, 'moved', implode(' ', $changes), $oldCoach);
            }
            if ($coachChanged) {
                $this->notifyChange($model, 'coach_changed', 'New coach: '.trim("{$model->coach->first_name} {$model->coach->last_name}").'.', $oldCoach);
            }
            if ($model->capacity > $oldCapacity) {
                app(BookingDesk::class)->promote($model);
            }

            return $model->refresh();
        });

        return response()->json(['session' => $this->payload($model)]);
    }

    public function freeCoaches(Gym $gym, int $session, SessionScheduler $scheduler): JsonResponse
    {
        $model = TrainingSession::findOrFail($session);
        $coaches = $this->coaches($gym)->filter(fn ($coach) => $scheduler->problems($coach->id, $model->room_id, $model->starts_at, $model->ends_at, $model->id) === [])
            ->map(fn ($coach) => ['id' => $coach->id, 'name' => trim("{$coach->first_name} {$coach->last_name}")])->values();

        return response()->json(['coaches' => $coaches]);
    }

    /** D-32: staff confirm a flagged session once its coach can run it again. */
    public function clearFlag(Gym $gym, int $session, SessionScheduler $scheduler, SessionFlagger $flagger): JsonResponse
    {
        $model = DB::transaction(function () use ($gym, $session, $scheduler, $flagger) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $model = TrainingSession::lockForUpdate()->findOrFail($session);
            abort_unless($model->flagged_at && $model->status === SessionStatus::Scheduled, 409, 'This session is not flagged.');
            if (! $this->coaches($gym)->contains('id', $model->coach_id)) {
                throw ValidationException::withMessages(['coach_id' => 'This coach is no longer on staff. Choose a new coach.']);
            }
            $problems = $scheduler->problems($model->coach_id, $model->room_id, $model->starts_at, $model->ends_at, $model->id);
            $blocking = [...($problems['starts_at'] ?? []), ...($problems['coach_id'] ?? [])];
            if ($blocking !== []) {
                throw ValidationException::withMessages(['flag' => $blocking]);
            }
            $model->update(['flagged_at' => null, 'flag_reason' => null]);
            $flagger->tellAthletes($model, 'flag_cleared', 'This session goes ahead as planned.');
            app(BookingDesk::class)->promote($model);

            return $model->refresh();
        });

        return response()->json(['session' => $this->payload($model)]);
    }

    public function cancel(Request $request, Gym $gym, int $session, BookingLedger $ledger): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $model = DB::transaction(function () use ($request, $gym, $session, $ledger, $data) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $model = TrainingSession::lockForUpdate()->findOrFail($session);
            abort_if($model->status === SessionStatus::Cancelled, 409, 'This session is already cancelled.');
            $this->notifyChange($model, 'cancelled', $model->starts_at->format('D j M Y, g:i A').'. Reason: '.$data['reason']);
            $model->update(['status' => SessionStatus::Cancelled, 'cancel_reason' => $data['reason'], 'flagged_at' => null, 'flag_reason' => null]);
            foreach ($model->bookings()->lockForUpdate()->get() as $booking) {
                if ($booking->status->isLive()) {
                    $booking->forceFill(['status' => BookingStatus::Cancelled, 'cancelled_by' => $request->user()->id, 'cancelled_at' => now()])->save();
                }
                $ledger->refund($booking, $request->user());
            }

            return $model;
        });

        return response()->json(['session' => $this->payload($model)]);
    }

    /** R-125: set a picture on this session, and on later scheduled ones in its series when asked. */
    public function thumbnail(Request $request, Gym $gym, int $session): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:'.config('offer.service_photos.max_kb')],
            'apply_to_series' => ['nullable', 'boolean'],
        ], ['photo.max' => 'The photo must be '.(config('offer.service_photos.max_kb') / 1024).' MB or smaller.']);

        return $this->changeThumbnail($request, $session, $request->file('photo')->store("training-sessions/{$gym->id}", 'public'));
    }

    public function destroyThumbnail(Request $request, Gym $gym, int $session): JsonResponse
    {
        $request->validate(['apply_to_series' => ['nullable', 'boolean']]);

        return $this->changeThumbnail($request, $session, null);
    }

    private function changeThumbnail(Request $request, int $session, ?string $path): JsonResponse
    {
        $model = TrainingSession::findOrFail($session);
        if ($model->status === SessionStatus::Cancelled) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            abort(409, 'Cancelled sessions cannot be edited.');
        }
        $targets = TrainingSession::whereKey($model->id)
            ->when($request->boolean('apply_to_series') && $model->series_id, fn ($query) => $query->orWhere(fn ($q) => $q
                ->where('series_id', $model->series_id)->where('status', SessionStatus::Scheduled)->where('starts_at', '>=', now())));
        $old = (clone $targets)->pluck('thumbnail_path')->filter()->unique();
        $targets->update(['thumbnail_path' => $path]);
        foreach ($old as $file) {
            if ($file !== $path && TrainingSession::where('thumbnail_path', $file)->doesntExist()) {
                Storage::disk('public')->delete($file);
            }
        }

        return response()->json(['session' => $this->payload($model->fresh())]);
    }

    private function notifyChange(TrainingSession $session, string $type, string $message, ?int $oldCoach = null): void
    {
        $ids = $session->bookings()->whereIn('status', ['requested', 'booked', 'waitlisted'])->pluck('user_id')
            ->push($session->coach_id)->push($oldCoach)->filter()->unique();
        foreach (User::whereIn('id', $ids)->get() as $user) {
            $user->notify(new SessionChanged($session, $type, $message));
        }
    }

    private function validated(Request $request, Gym $gym, bool $updating = false, ?string $currentDiscipline = null): array
    {
        if (! $request->exists('capacity') && $request->input('type') === 'private_coaching') {
            $request->merge(['capacity' => 1]);
        }
        $inGym = fn (string $table) => Rule::exists($table, 'id')->where('gym_id', $gym->id);
        $data = $request->validate([
            'gym_service_id' => ['nullable', 'integer', $inGym('gym_services')],
            'type' => ['required', Rule::in(array_keys($gym->activityTypeOptions()))],
            'name' => ['required', 'string', 'max:120'], 'discipline' => ['nullable', 'string', Rule::in([...array_keys(config('verification.disciplines')), ...array_filter([$currentDiscipline])])], // R-124
            'level' => ['required', Rule::in(array_keys(config('offer.levels')))],
            'coach_id' => ['required', 'integer', Rule::in($this->coaches($gym)->pluck('id')->all())],
            'room_id' => ['required', 'integer', $inGym('rooms')->where('is_active', true)],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'between:1,500'],
            'allowed_plan_ids' => ['nullable', 'array', 'max:500'],
            'allowed_plan_ids.*' => ['required', 'integer', 'distinct', $inGym('membership_plans')],
            'content' => ['nullable', 'string', 'max:10000'], 'requirements' => ['nullable', 'string', 'max:10000'],
            'bring' => ['nullable', 'array', 'max:100'], 'bring.*' => ['required', 'string', 'max:255'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'needs_approval' => ['nullable', 'boolean'],
            'repeat' => $updating ? ['prohibited'] : ['nullable', 'array:weekdays,until'],
            'repeat.weekdays' => [Rule::requiredIf($request->input('repeat') !== null), 'array', 'min:1', 'max:7'],
            'repeat.weekdays.*' => ['required', 'distinct', Rule::in(array_keys(config('hr.weekdays')))],
            'repeat.until' => [Rule::requiredIf($request->input('repeat') !== null), 'date_format:Y-m-d'],
        ]);
        $start = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone'));
        $end = Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone'));
        if (! $start->isSameDay($end)) {
            throw ValidationException::withMessages(['ends_at' => 'The session must start and end on the same day.']);
        }
        if (isset($data['allowed_plan_ids'])) {
            $data['allowed_plan_ids'] = array_map('intval', $data['allowed_plan_ids']);
        }

        return $data;
    }

    private function payload(TrainingSession $session): array
    {
        $session->loadMissing(['coach', 'room', 'service']);

        return [
            ...$session->attributesToArray(),
            // Gym-local wall time, matching work schedules and date inputs.
            'starts_at' => $session->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $session->ends_at->format('Y-m-d H:i:s'),
            'phase' => $session->phase(),
            'thumbnail_url' => $session->thumbnailUrl(), // R-125
            'coach' => ['id' => $session->coach->id, 'name' => trim("{$session->coach->first_name} {$session->coach->last_name}")],
            'room' => ['id' => $session->room->id, 'name' => $session->room->name],
            'service' => $session->service ? ['id' => $session->service->id, 'name' => $session->service->name] : null,
        ];
    }
}
