<?php

namespace App\Models;

use App\Enums\OvertimeKind;
use App\Enums\OvertimeStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-57: extra time waiting for a decision, or already decided. */
#[Fillable([
    'user_id', 'work_date', 'staff_attendance_id', 'kind', 'requested_minutes', 'approved_minutes',
    'reason', 'status', 'decided_by', 'decided_at', 'decision_note',
])]
class OvertimeRequest extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'kind' => OvertimeKind::class,
            'status' => OvertimeStatus::class,
            'requested_minutes' => 'integer',
            'approved_minutes' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(StaffAttendance::class, 'staff_attendance_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
