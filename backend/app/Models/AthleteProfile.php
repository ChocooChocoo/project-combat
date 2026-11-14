<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** An athlete's profile in one gym, made on approval from the application (R-33, R-40). */
#[Fillable([
    'user_id', 'emergency_contacts', 'background', 'current_rank', 'level', 'training_history', 'health_answers', 'health_notes',
    'weight_kg', 'height_cm', 'wins', 'losses', 'draws', 'experience_years',
])]
#[Hidden(['medical_file_path'])]
class AthleteProfile extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'emergency_contacts' => 'array',
            'health_answers' => 'array',
            'weight_kg' => 'decimal:1',
            'height_cm' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'draws' => 'integer',
            'experience_years' => 'integer',
        ];
    }
}
