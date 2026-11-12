<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-118: one training goal for an athlete, shared by the athlete and their coaches. */
#[Fillable(['title', 'description', 'target_on', 'status'])]
class TrainingGoal extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['target_on' => 'date', 'achieved_on' => 'date'];
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(GoalMilestone::class)->orderBy('position')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Achieved once every milestone is done; back to active if one is undone. Goals with no milestones are set by hand. */
    public function syncStatusFromMilestones(): void
    {
        $milestones = $this->milestones()->get();

        if ($milestones->isEmpty() || $this->status === 'dropped') {
            return;
        }

        $allDone = $milestones->every(fn (GoalMilestone $milestone) => $milestone->done_on !== null);

        if ($allDone && $this->status !== 'achieved') {
            $this->forceFill(['status' => 'achieved', 'achieved_on' => today()->toDateString()])->save();
        } elseif (! $allDone && $this->status === 'achieved') {
            $this->forceFill(['status' => 'active', 'achieved_on' => null])->save();
        }
    }
}
