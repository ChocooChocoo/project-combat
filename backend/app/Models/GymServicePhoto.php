<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One gallery photo of a service (R-28). The stored path stays private. */
#[Fillable(['gym_service_id', 'path', 'sort'])]
#[Hidden(['path'])]
class GymServicePhoto extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GymService::class, 'gym_service_id');
    }
}
