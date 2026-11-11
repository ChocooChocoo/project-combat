<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Enums\SessionStatus;
use App\Models\AthleteProfile;
use App\Models\Membership;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\BookingRequested;
use App\Notifications\BookingUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** R-48 to R-50: every place-changing action locks the occurrence first. */
class BookingDesk
{
    public function __construct(private SessionScheduler $scheduler) {}

    public function membership(User $user): ?Membership
    {
        return Membership::where('user_id', $user->id)->where('status', MembershipStatus::Active)->first();
    }

    /** @return string[] */
    public function eligibility(User $user, TrainingSession $session): array
    {
        $reasons = [];
        if ($session->status !== SessionStatus::Scheduled) {
            $reasons[] = 'This session is cancelled.';
        }
        if ($session->starts_at->lte(now())) {
            $reasons[] = 'This session has already started.';
        }
        if ($session->flagged_at) {
            $reasons[] = 'This session needs a staff review before bookings can be made.';
        }
        $membership = $this->membership($user);
        if (! $membership) {
            $reasons[] = 'You need an active membership at this gym.';
        } else {
            if ($session->allowed_plan_ids && ! in_array($membership->membership_plan_id, $session->allowed_plan_ids, true)) {
                $reasons[] = 'Your membership plan is not eligible for this session.';
            }
            if ($membership->sessions_remaining !== null && $membership->sessions_remaining <= 0) {
                $reasons[] = 'Your membership has no sessions remaining.';
            }
        }
        if ($reason = $this->levelProblem($user, $session)) {
            $reasons[] = $reason;
        }
        $problems = $this->scheduler->problems($session->coach_id, $session->room_id, $session->starts_at, $session->ends_at, $session->id);

        return [...$reasons, ...($problems['starts_at'] ?? []), ...($problems['coach_id'] ?? [])];
    }

    /** D-35: the athlete's level must be at or above the session's; no level set counts as beginner. */
    private function levelProblem(User $user, TrainingSession $session): ?string
    {
        $order = array_keys(config('offer.athlete_levels'));
        $needed = array_search($session->level, $order, true);
        if ($needed === false) {
            return null; // 'all' levels
        }
        $level = AthleteProfile::where('user_id', $user->id)->value('level') ?? $order[0];
        if (array_search($level, $order, true) >= $needed) {
            return null;
        }

        return 'This session is for '.config("offer.athlete_levels.{$session->level}").' athletes and above.';
    }

    public function needsApproval(TrainingSession $session): bool
    {
        return $session->needs_approval ?? $session->gym->booking_approval;
    }

    public function book(User $user, TrainingSession $session): SessionBooking
    {
        return DB::transaction(function () use ($user, $session) {
            // R-48: MySQL serializes last-place requests; SQLite tests cannot prove row locking.
            $session = TrainingSession::lockForUpdate()->findOrFail($session->id);
            $reasons = $this->eligibility($user, $session);
            if ($reasons !== []) {
                throw ValidationException::withMessages(['eligibility' => $reasons]);
            }
            $booking = $session->bookings()->where('user_id', $user->id)->first();
            if ($booking?->status->isLive()) {
                throw ValidationException::withMessages(['booking' => 'You already have a booking or waiting-list place for this session.']);
            }
            $status = $session->placesTaken() < $session->capacity
                ? $this->placeStatus($session) : BookingStatus::Waitlisted;
            $waitlistedAt = null;
            if ($status === BookingStatus::Waitlisted) {
                if ($session->type === 'private_coaching') {
                    throw ValidationException::withMessages(['booking' => 'This private coaching session is not available.']);
                }
                $waitlistedAt = now();
                $last = $session->bookings()->where('status', BookingStatus::Waitlisted)->max('waitlisted_at');
                // R-50: a reused row rejoins at the back, even inside the same clock tick.
                if ($last && $waitlistedAt->lte(Carbon::parse($last))) {
                    $waitlistedAt = Carbon::parse($last)->addMicrosecond();
                }
            }
            $booking ??= new SessionBooking;
            $booking->forceFill([
                'training_session_id' => $session->id, 'user_id' => $user->id,
                'membership_id' => $this->membership($user)->id, 'status' => $status,
                'waitlisted_at' => $waitlistedAt,
                'fee' => $session->fee ?? 0, 'decided_by' => null, 'decided_at' => null,
                'decision_reason' => null, 'cancelled_by' => null, 'cancelled_at' => null,
            ])->save();
            $booking->setRelation('session', $session);
            $this->notify($booking, $status->value);

            return $booking;
        });
    }

    public function decide(SessionBooking $booking, User $by, string $decision, ?string $reason): SessionBooking
    {
        abort_unless($by->hasPermission($booking->gym_id, 'training.bookings.approve'), 403);
        if ($decision === 'reject' && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Give a reason for rejecting this booking.']);
        }

        return DB::transaction(function () use ($booking, $by, $decision, $reason) {
            $session = TrainingSession::lockForUpdate()->findOrFail($booking->training_session_id);
            $booking = SessionBooking::lockForUpdate()->findOrFail($booking->id);
            if ($booking->status !== BookingStatus::Requested) {
                throw ValidationException::withMessages(['booking' => 'Only requested bookings can be decided.']);
            }
            if ($decision === 'approve' && ($reasons = $this->eligibility($booking->user, $session)) !== []) {
                throw ValidationException::withMessages(['eligibility' => $reasons]);
            }
            $booking->forceFill([
                'status' => $decision === 'approve' ? BookingStatus::Booked : BookingStatus::Rejected,
                'decided_by' => $by->id, 'decided_at' => now(), 'decision_reason' => $reason,
            ])->save();
            $this->notify($booking, $decision === 'approve' ? 'approved' : 'rejected');
            if ($decision === 'reject') {
                $this->promote($session);
            }

            return $booking;
        });
    }

    public function cancellationReason(SessionBooking $booking, User $by): ?string
    {
        if (! $booking->status->isLive()) {
            return 'This booking is no longer active.';
        }
        if ($by->hasPermission($booking->gym_id, 'training.bookings.manage')) {
            return null;
        }
        if ($by->id !== $booking->user_id) {
            return 'You can only cancel your own booking.';
        }
        if (now()->gte($booking->session->starts_at->copy()->subHours($booking->gym->cancel_cutoff_hours))) {
            return 'The cancellation cut-off has passed. Ask gym staff to cancel.';
        }

        return null;
    }

    public function cancel(SessionBooking $booking, User $by): SessionBooking
    {
        abort_unless($by->id === $booking->user_id || $by->hasPermission($booking->gym_id, 'training.bookings.manage'), 403);

        return DB::transaction(function () use ($booking, $by) {
            $session = TrainingSession::lockForUpdate()->findOrFail($booking->training_session_id);
            $booking = SessionBooking::lockForUpdate()->findOrFail($booking->id);
            $booking->setRelation('session', $session);
            if ($reason = $this->cancellationReason($booking, $by)) {
                throw ValidationException::withMessages(['booking' => $reason]);
            }
            $booking->forceFill(['status' => BookingStatus::Cancelled, 'cancelled_by' => $by->id, 'cancelled_at' => now()])->save();
            $this->notify($booking, 'cancelled');
            $this->promote($session);

            return $booking;
        });
    }

    /** R-50: caller holds the session lock and transaction throughout promotion. */
    public function promote(TrainingSession $session): void
    {
        if ($session->status !== SessionStatus::Scheduled || $session->starts_at->lte(now()) || $session->flagged_at) {
            return;
        }
        $problems = $this->scheduler->problems($session->coach_id, $session->room_id, $session->starts_at, $session->ends_at, $session->id);
        if (! empty($problems['starts_at']) || ! empty($problems['coach_id'])) {
            return;
        }
        while ($session->placesTaken() < $session->capacity) {
            $next = $session->bookings()->where('status', BookingStatus::Waitlisted)->orderBy('waitlisted_at')->orderBy('id')->lockForUpdate()->first();
            if (! $next) {
                break;
            }
            $reasons = $this->eligibility($next->user, $session);
            // R-48 still applies if membership eligibility changed while waiting.
            if ($reasons !== []) {
                $next->forceFill(['status' => BookingStatus::Rejected, 'decided_at' => now(), 'decision_reason' => implode(' ', $reasons)])->save();
                $this->notify($next, 'rejected');

                continue;
            }
            $next->forceFill(['status' => $this->placeStatus($session)])->save();
            $this->notify($next, 'promoted');
        }
    }

    private function placeStatus(TrainingSession $session): BookingStatus
    {
        return $this->needsApproval($session) ? BookingStatus::Requested : BookingStatus::Booked;
    }

    private function notify(SessionBooking $booking, string $type): void
    {
        $booking->user->notify(new BookingUpdated($booking, $type));
        if ($booking->status === BookingStatus::Requested) {
            foreach ($booking->gym->peopleWithPermission('training.bookings.approve') as $approver) {
                $approver->notify(new BookingRequested($booking));
            }
        }
    }
}
