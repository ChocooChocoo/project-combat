<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-48: state and fee are set by BookingDesk, never from request input. */
class SessionBooking extends Model
{
    use BelongsToGym;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class, 'fee' => 'decimal:2',
            'waitlisted_at' => 'datetime', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime',
            'check_in_at' => 'datetime', 'check_out_at' => 'datetime', 'reminder_sent_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function attendanceBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScope('gym');
    }
}
