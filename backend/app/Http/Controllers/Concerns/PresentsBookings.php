<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\BookingDesk;
use App\Support\BookingLedger;
use Illuminate\Support\Facades\Storage;

/** R-47: session details never expose HR/pay or other athletes' bookings. */
trait PresentsBookings
{
    use PresentsMemberships;

    protected function bookingPayload(SessionBooking $booking, User $viewer): array
    {
        $reason = app(BookingDesk::class)->cancellationReason($booking, $viewer);
        $ledger = app(BookingLedger::class);

        return [
            'id' => $booking->id, 'gym_id' => $booking->gym_id,
            'training_session_id' => $booking->training_session_id, 'user_id' => $booking->user_id,
            'status' => $booking->status->value, 'fee' => $booking->fee,
            'paid' => number_format($ledger->paid($booking), 2, '.', ''),
            'balance' => number_format($ledger->balance($booking), 2, '.', ''),
            'payment_state' => $ledger->state($booking),
            'payments' => $booking->payments()->with('receivedBy')->orderByDesc('id')->get()->map(fn ($payment) => $this->paymentPayload($payment))->all(),
            'waitlisted_at' => $booking->waitlisted_at?->format('Y-m-d H:i:s'),
            'decided_at' => $booking->decided_at?->toIso8601String(), 'decided_by' => $booking->decided_by,
            'decision_reason' => $booking->decision_reason,
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(), 'cancelled_by' => $booking->cancelled_by,
            'can_cancel' => $reason === null, 'cancel_reason' => $reason,
            'check_in_at' => $booking->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $booking->check_out_at?->format('Y-m-d H:i:s'),
            'attendance' => $booking->attendance, 'attendance_method' => $booking->attendance_method,
            'attendance_by' => $booking->attendanceBy ? trim("{$booking->attendanceBy->first_name} {$booking->attendanceBy->last_name}") : null,
        ];
    }

    protected function sessionSummary(TrainingSession $session): array
    {
        $session->loadMissing(['coach', 'room']);

        return [
            'id' => $session->id, 'name' => $session->name, 'type' => $session->type,
            'type_label' => $session->gym->activityTypeLabel($session->type),
            'discipline' => $session->discipline,
            'thumbnail_url' => $session->thumbnailUrl(), // R-125
            'discipline_label' => config("verification.disciplines.{$session->discipline}", $session->discipline),
            'level' => $session->level,
            'level_label' => config("offer.levels.{$session->level}", $session->level),
            'starts_at' => $session->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $session->ends_at->format('Y-m-d H:i:s'),
            'status' => $session->status->value, 'phase' => $session->phase(), 'capacity' => $session->capacity, 'fee' => $session->fee,
            'flagged_at' => $session->flagged_at?->toIso8601String(), 'flag_reason' => $session->flag_reason,
            'cancel_reason' => $session->cancel_reason,
            'coach' => ['id' => $session->coach_id, 'name' => trim("{$session->coach->first_name} {$session->coach->last_name}")],
            'room' => ['id' => $session->room_id, 'name' => $session->room->name],
        ];
    }

    protected function timetablePayload(TrainingSession $session, Gym $gym, User $viewer, bool $details = false): array
    {
        $desk = app(BookingDesk::class);
        $mine = $session->bookings()->where('user_id', $viewer->id)->first();
        $left = max(0, $session->capacity - $session->placesTaken());
        $data = [
            ...$this->sessionSummary($session), 'places_left' => $left,
            'waitlist_count' => $session->bookings()->where('status', BookingStatus::Waitlisted)->count(),
            'waiting_list_open' => $session->type !== 'private_coaching' && $left === 0 && $session->status === SessionStatus::Scheduled
                && $session->starts_at->gt(now()) && ! $session->flagged_at,
            'needs_approval' => $desk->needsApproval($session),
            'my_booking' => $mine ? $this->bookingPayload($mine, $viewer) : null,
        ];
        if (! $details) {
            return $data;
        }
        $profile = EmployeeProfile::where('user_id', $session->coach_id)->where('coach_public', true)->first();
        $weekdays = $session->series_id
            ? TrainingSession::where('series_id', $session->series_id)->get()->sortBy(fn ($s) => $s->starts_at->dayOfWeekIso)
                ->map(fn ($s) => $s->starts_at->format('D'))->unique()->values()->all()
            : [];

        return [
            ...$data,
            'coach' => [...$data['coach'],
                'photo_url' => $profile?->coach_photo_path ? Storage::disk('public')->url($profile->coach_photo_path) : null,
                'rank' => $profile?->coach_rank, 'specialisation' => $profile?->coach_specialisation,
                'experience' => $profile?->coach_experience, 'bio' => $profile?->coach_bio,
            ],
            'duration_minutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
            'repeat_note' => $weekdays ? 'Repeats: '.implode(', ', $weekdays) : null,
            'content' => $session->content, 'requirements' => $session->requirements, 'bring' => $session->bring ?? [],
            'allowed_plan_ids' => $session->allowed_plan_ids ?? [],
            'eligibility_reasons' => $desk->eligibility($viewer, $session),
            'cancel_cutoff_hours' => $gym->cancel_cutoff_hours,
        ];
    }
}
