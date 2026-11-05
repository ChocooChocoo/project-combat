<?php

namespace App\Models;

use App\Enums\AthleteEntryType;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A promotion, skill assessment, competition or achievement in an athlete's record (R-37 to R-39). */
#[Fillable(['user_id', 'type', 'discipline', 'title', 'division', 'result', 'occurred_on', 'notes', 'recorded_by'])]
class AthleteEntry extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'type' => AthleteEntryType::class,
            'occurred_on' => 'date',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
