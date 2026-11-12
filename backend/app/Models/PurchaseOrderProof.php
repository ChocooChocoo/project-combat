<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-114: one receipt or delivery proof file on a purchase order. Filled by the system only. */
class PurchaseOrderProof extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
