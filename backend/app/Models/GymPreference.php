<?php

namespace App\Models;

use Database\Factories\GymPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What one person wants from a gym, kept between visits (R-98). Not tied to one gym. */
#[Fillable(['discipline', 'min_price', 'max_price', 'preferred_times'])]
class GymPreference extends Model
{
    /** @use HasFactory<GymPreferenceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'preferred_times' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
