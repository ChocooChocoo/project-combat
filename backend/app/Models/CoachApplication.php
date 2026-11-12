<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-117: a member's request to coach at a gym. Status and decision fields are set by the system. */
#[Fillable(['experience', 'rank', 'specialisation', 'bio', 'availability'])]
class CoachApplication extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['availability' => 'array', 'decided_at' => 'datetime'];
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
