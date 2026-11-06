<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An append-only record of a correction to one attendance day (R-56). */
#[Fillable(['staff_attendance_id', 'changed_by', 'before', 'after', 'reason'])]
class StaffAttendanceChange extends Model
{
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(StaffAttendance::class, 'staff_attendance_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
