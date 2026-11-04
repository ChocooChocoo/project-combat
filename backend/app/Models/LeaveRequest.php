<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Models\Concerns\BelongsToGym;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An employee's ask for leave, and the decision on it (R-25). Attachments stay private. */
#[Fillable(['user_id', 'type', 'starts_on', 'ends_on', 'working_days', 'reason', 'status', 'decision_note'])]
#[Hidden(['attachment_path'])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use BelongsToGym, HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'working_days' => 'integer',
            'status' => LeaveRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
