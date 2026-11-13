<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** R-42: one occurrence; series_id groups repeats without coupling their edits. */
#[Fillable([
    'gym_service_id', 'series_id', 'type', 'name', 'discipline', 'level', 'coach_id', 'room_id',
    'starts_at', 'ends_at', 'capacity', 'allowed_plan_ids', 'content', 'requirements', 'bring',
    'fee', 'needs_approval', 'status', 'cancel_reason', 'flagged_at', 'flag_reason', 'created_by',
])]
class TrainingSession extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'flagged_at' => 'datetime',
            'capacity' => 'integer', 'allowed_plan_ids' => 'array', 'bring' => 'array',
            'fee' => 'decimal:2', 'needs_approval' => 'boolean', 'status' => SessionStatus::class,
        ];
    }

    /** R-125 */
    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null;
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GymService::class, 'gym_service_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(SessionBooking::class);
    }

    /** R-49: a pending decision reserves a place too. */
    public function placesTaken(): int
    {
        return $this->bookings()->whereIn('status', [BookingStatus::Requested, BookingStatus::Booked])->count();
    }

    /** Shown status from the clock (Asia/Manila); the stored status stays scheduled or cancelled. */
    public function phase(): string
    {
        return match (true) {
            $this->status === SessionStatus::Cancelled => 'cancelled',
            now()->lt($this->starts_at) => 'scheduled',
            now()->lt($this->ends_at) => 'ongoing',
            default => 'ended',
        };
    }
}
