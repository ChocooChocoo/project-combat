<?php

namespace App\Http\Controllers\Gym;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Concerns\PresentsBookings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\SessionBooking;
use App\Models\SessionNote;
use App\Models\TrainingSession;
use App\Support\BookingDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** R-47 to R-50: athlete timetable and permission-checked staff booking desk. */
class BookingController extends Controller
{
    use PresentsBookings, PresentsHr;

    public function timetable(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : today();
        $to = isset($data['to']) ? Carbon::parse($data['to']) : $from->copy()->addDays(13);
        abort_if($to->lt($from) || $to->diffInDays($from, true) > 93, 422, 'Choose an end date on or after the start, within 93 days.');
        $sessions = TrainingSession::with(['coach', 'room', 'gym'])->where('status', SessionStatus::Scheduled)
            ->where('starts_at', '>', now())->where('starts_at', '>=', $from)->where('starts_at', '<', $to->copy()->addDay())
            ->orderBy('starts_at')->orderBy('id')->get();

        return response()->json([
            'sessions' => $sessions->map(fn ($session) => $this->timetablePayload($session, $gym, $request->user())),
            'coaches' => $this->coaches($gym)->map(fn ($coach) => ['id' => $coach->id, 'name' => trim("{$coach->first_name} {$coach->last_name}")])->values(),
            'private_services' => $gym->gymServices()->where('activity_type', 'private_coaching')->orderBy('name')
                ->get(['id', 'name', 'duration_minutes', 'price', 'price_included']),
        ]);
    }

    public function show(Request $request, Gym $gym, int $session): JsonResponse
    {
        return response()->json(['session' => $this->timetablePayload(TrainingSession::findOrFail($session), $gym, $request->user(), true)]);
    }

    public function book(Request $request, Gym $gym, int $session, BookingDesk $desk): JsonResponse
    {
        $booking = $desk->book($request->user(), TrainingSession::findOrFail($session));

        return response()->json(['booking' => $this->bookingPayload($booking, $request->user())], 201);
    }

    public function cancel(Request $request, Gym $gym, int $booking, BookingDesk $desk): JsonResponse
    {
        $model = $desk->cancel(SessionBooking::findOrFail($booking), $request->user());

        return response()->json(['booking' => $this->bookingPayload($model, $request->user())]);
    }

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', Rule::enum(BookingStatus::class)]]);
        $bookings = SessionBooking::with(['session.coach', 'session.room', 'user', 'gym'])
            ->where('status', $data['status'] ?? BookingStatus::Requested)->orderBy('created_at')->orderBy('id')->get();

        return response()->json(['bookings' => $bookings->map(fn ($booking) => $this->staffPayload($booking, $request))]);
    }

    public function decide(Request $request, Gym $gym, int $booking, BookingDesk $desk): JsonResponse
    {
        $model = SessionBooking::findOrFail($booking);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ]);
        $model = $desk->decide($model, $request->user(), $data['decision'], $data['reason'] ?? null);

        return response()->json(['booking' => $this->bookingPayload($model, $request->user())]);
    }

    public function roster(Request $request, Gym $gym, int $session): JsonResponse
    {
        $model = TrainingSession::findOrFail($session);
        $bookings = $model->bookings()->with(['user', 'session', 'gym'])
            ->orderByRaw("CASE status WHEN 'booked' THEN 0 WHEN 'requested' THEN 1 WHEN 'waitlisted' THEN 2 ELSE 3 END")
            ->orderBy('waitlisted_at')->orderBy('id')->get();
        $position = 0;

        return response()->json(['bookings' => $bookings->map(function ($booking) use ($request, &$position) {
            return [...$this->staffPayload($booking, $request),
                'waitlist_position' => $booking->status === BookingStatus::Waitlisted ? ++$position : null];
        }), 'notes' => SessionNote::with(['session', 'author'])->where('training_session_id', $model->id)
            ->latest()->get()->map(fn ($note) => $note->payload())]);
    }

    private function staffPayload(SessionBooking $booking, Request $request): array
    {
        return [
            ...$this->bookingPayload($booking, $request->user()),
            'athlete' => ['id' => $booking->user_id, 'name' => trim("{$booking->user->first_name} {$booking->user->last_name}")],
            'session' => $this->sessionSummary($booking->session),
        ];
    }
}
