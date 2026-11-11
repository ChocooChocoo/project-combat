<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\GymVisit;
use App\Models\SessionBooking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceLedger
{
    /** R-52, R-53: callers hold the booking lock; pack debits and corrections are atomic. */
    public function mark(SessionBooking $booking, string $attendance, User $by, string $method, ?Carbon $at = null): void
    {
        $attended = in_array($attendance, ['present', 'late'], true);
        $membership = $booking->membership()->lockForUpdate()->first();
        if ($membership && $membership->sessions_remaining !== null) {
            if ($attended && ! $booking->session_pack_used && $membership->sessions_remaining > 0) {
                $membership->decrement('sessions_remaining');
                $booking->session_pack_used = true;
            } elseif (! $attended && $booking->session_pack_used) {
                $membership->increment('sessions_remaining');
                $booking->session_pack_used = false;
            }
        }
        $booking->forceFill([
            'attendance' => $attendance, 'attendance_by' => $by->id, 'attendance_method' => $method,
            'check_in_at' => $attended ? ($at ?? now()) : null,
            'check_out_at' => $attended ? $booking->check_out_at : null,
        ])->save();
    }

    public function checkIn(User $user, User $by, string $method): array
    {
        return DB::transaction(function () use ($user, $by, $method) {
            // Serialize account and code scans, including visits without a booking row.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $booking = SessionBooking::where('user_id', $user->id)->where('status', BookingStatus::Booked)
                ->whereHas('session', fn ($q) => $q->where('status', 'scheduled')
                    ->where('starts_at', '<=', now()->addMinutes(30))->where('ends_at', '>=', now()))
                ->with('session')->orderBy('training_session_id')->lockForUpdate()->first();
            if ($booking) {
                // A session check-in ends any open gym visit, so the desk is never stuck.
                GymVisit::where('user_id', $user->id)->whereNull('checked_out_at')->update(['checked_out_at' => now()]);
                if ($booking->check_in_at) {
                    $this->duplicate($booking->check_in_at);
                }
                $late = now()->gt($booking->session->starts_at->copy()->addMinutes($booking->gym->late_grace_minutes ?? 10));
                $this->mark($booking, $late ? 'late' : 'present', $by, $method);

                return $this->bookingRow($booking->load(['user', 'attendanceBy']));
            }
            $previous = GymVisit::where('user_id', $user->id)->whereDate('checked_in_at', today())->whereNull('checked_out_at')->first();
            if ($previous) {
                $this->duplicate($previous->checked_in_at);
            }
            $visit = GymVisit::create(['user_id' => $user->id, 'checked_in_at' => now(), 'checked_in_by' => $by->id, 'method' => $method]);

            return $this->visitRow($visit->load(['user', 'checkedInBy']));
        });
    }

    public function checkOutVisit(int $visit): array
    {
        return DB::transaction(function () use ($visit) {
            $model = GymVisit::lockForUpdate()->findOrFail($visit);
            abort_if($model->checked_out_at, 422, 'Already checked out.');
            $model->forceFill(['checked_out_at' => now()])->save();

            return $this->visitRow($model->load(['user', 'checkedInBy']));
        });
    }

    private function duplicate(Carbon $at): never
    {
        throw ValidationException::withMessages(['code' => 'Already checked in at '.$at->format('g:i A').'.']);
    }

    /** R-54: one shape for the desk, attendance history and athlete record. */
    public function rows(array $filters = []): Collection
    {
        $bookings = SessionBooking::with(['user', 'session', 'attendanceBy'])->whereNotNull('attendance')
            ->when($filters['checked_in_on'] ?? null, fn ($q, $date) => $q->whereDate('check_in_at', $date))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->whereHas('session', function ($q) use ($filters) {
                $q->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('starts_at', '>=', $from))
                    ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('starts_at', '<=', $to))
                    ->when($filters['session_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
                    ->when($filters['gym_service_id'] ?? null, fn ($q, $id) => $q->where('gym_service_id', $id));
            })->get()->map(fn ($booking) => $this->bookingRow($booking));
        $visits = collect();
        if (empty($filters['session_id']) && empty($filters['gym_service_id'])) {
            $visits = GymVisit::with(['user', 'checkedInBy'])
                ->when($filters['checked_in_on'] ?? null, fn ($q, $date) => $q->whereDate('checked_in_at', $date))
                ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('checked_in_at', '>=', $from))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('checked_in_at', '<=', $to))
                ->get()->map(fn ($visit) => $this->visitRow($visit));
        }

        return $bookings->concat($visits)->sortByDesc('date')->values();
    }

    public function bookingRow(SessionBooking $booking): array
    {
        return [
            'id' => 'booking-'.$booking->id, 'booking_id' => $booking->id,
            'member' => ['id' => $booking->user_id, 'name' => trim("{$booking->user->first_name} {$booking->user->last_name}")],
            'session' => ['id' => $booking->training_session_id, 'name' => $booking->session->name],
            'date' => $booking->session->starts_at->format('Y-m-d H:i:s'),
            'check_in_at' => $booking->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $booking->check_out_at?->format('Y-m-d H:i:s'),
            'attendance' => $booking->attendance, 'method' => $booking->attendance_method,
            'by' => $booking->attendanceBy ? trim("{$booking->attendanceBy->first_name} {$booking->attendanceBy->last_name}") : null,
        ];
    }

    public function visitRow(GymVisit $visit): array
    {
        return [
            'id' => 'visit-'.$visit->id, 'booking_id' => null,
            'member' => ['id' => $visit->user_id, 'name' => trim("{$visit->user->first_name} {$visit->user->last_name}")],
            'session' => null, 'date' => $visit->checked_in_at->format('Y-m-d H:i:s'),
            'check_in_at' => $visit->checked_in_at->format('Y-m-d H:i:s'), 'check_out_at' => $visit->checked_out_at?->format('Y-m-d H:i:s'),
            'attendance' => 'gym_visit', 'method' => $visit->method,
            'by' => $visit->checkedInBy ? trim("{$visit->checkedInBy->first_name} {$visit->checkedInBy->last_name}") : null,
        ];
    }
}
