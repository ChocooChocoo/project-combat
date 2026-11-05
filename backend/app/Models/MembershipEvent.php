<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change in a membership's history (R-36). */
#[Fillable(['membership_id', 'type', 'by_user_id', 'note'])]
class MembershipEvent extends Model
{
    use BelongsToGym;

    public function byUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_user_id');
    }
}
