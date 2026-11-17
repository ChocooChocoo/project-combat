<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** (R-147) Proof of injury: a medical certificate or another supporting document. Filled by the system only. */
class InjuryFile extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function injury(): BelongsTo
    {
        return $this->belongsTo(Injury::class);
    }
}
