<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-118: one step toward a training goal. */
#[Fillable(['title', 'due_on', 'position'])]
class GoalMilestone extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_on' => 'date'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(TrainingGoal::class, 'training_goal_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
