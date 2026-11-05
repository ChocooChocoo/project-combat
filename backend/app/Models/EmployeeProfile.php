<?php

namespace App\Models;

use App\Enums\PayBasis;
use App\Models\Concerns\BelongsToGym;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An employee's employment record in one gym, plus the public coach profile (R-23). */
#[Fillable([
    'job_title', 'started_on', 'pay_basis', 'monthly_rate', 'per_session_rate',
    'coach_photo_path', 'coach_experience', 'coach_rank', 'coach_specialisation', 'coach_bio', 'coach_public',
])]
class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use BelongsToGym, HasFactory;

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'pay_basis' => PayBasis::class,
            'monthly_rate' => 'decimal:2',
            'per_session_rate' => 'decimal:2',
            'coach_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCoach(): bool
    {
        return filled($this->coach_experience)
            || filled($this->coach_rank)
            || filled($this->coach_specialisation)
            || filled($this->coach_bio)
            || filled($this->coach_photo_path);
    }
}
