<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One employee's working day (R-56). The unique key is gym, employee and date. */
#[Fillable([
    'user_id', 'work_date', 'scheduled_start', 'scheduled_end', 'clock_in_at', 'clock_out_at',
    'status', 'late_minutes', 'undertime_minutes', 'worked_minutes', 'missing_clock_out',
    'source', 'explanation', 'recorded_by',
])]
class StaffAttendance extends Model
{
    use BelongsToGym;

    /** The table is singular on purpose: one row per employee per day. */
    protected $table = 'staff_attendance';

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'missing_clock_out' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(StaffAttendanceChange::class)->orderByDesc('id');
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }
}
