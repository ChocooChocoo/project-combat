<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** An athlete's profile in one gym, made on approval from the application (R-33, R-40). */
#[Fillable([
    'user_id', 'emergency_contacts', 'background', 'current_rank', 'training_history', 'health_answers', 'health_notes',
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
        ];
    }
}
