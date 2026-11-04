<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One weekday of an employee's weekly working hours (R-24). */
#[Fillable(['user_id', 'weekday', 'start_time', 'end_time'])]
class WorkSchedule extends Model
{
    /** @use HasFactory<WorkScheduleFactory> */
    use BelongsToGym, HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
