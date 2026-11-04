<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Database\Factories\GymServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A service the gym sells, with everything needed before it can be published (R-28). */
#[Fillable([
    'name', 'discipline', 'activity_type', 'overview', 'level', 'duration_minutes', 'frequency',
    'included', 'bring', 'price', 'price_included', 'cover_photo_path',
])]
class GymService extends Model
{
    /** @use HasFactory<GymServiceFactory> */
    use BelongsToGym, HasFactory;

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'included' => 'array',
            'bring' => 'array',
            'price' => 'decimal:2',
            'price_included' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GymServicePhoto::class);
    }
}
