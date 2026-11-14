<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A verification file (R-15), kept on the private "local" disk.
 * Not on BelongsToGym: the applicant and the administrator handle these
 * outside any gym workspace. GymApplicationPolicy guards them instead.
 */
#[Fillable(['type', 'path', 'original_name', 'mime', 'size', 'expires_on', 'ocr_flags'])]
#[Hidden(['path'])]
class GymDocument extends Model
{
    protected function casts(): array
    {
        return ['expires_on' => 'date:Y-m-d', 'ocr_flags' => 'array', 'admin_viewed_at' => 'datetime'];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
