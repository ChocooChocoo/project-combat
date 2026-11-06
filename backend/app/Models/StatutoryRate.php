<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R-60: an effective-dated statutory table. Platform rows have a null gym_id;
 * a gym may add its own row. Rows are insert-only so released history keeps
 * pointing at exactly the table that was used.
 */
#[Fillable(['gym_id', 'kind', 'effective_from', 'brackets', 'source', 'created_by'])]
class StatutoryRate extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Statutory rates cannot be changed. Add a new effective-dated rate.'));
        static::deleting(fn () => throw new \LogicException('Statutory rates cannot be deleted. Add a new effective-dated rate.'));
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'brackets' => 'array',
        ];
    }

    /** Platform rows plus this gym's own rows. Never relies on a global scope. */
    public function scopeForGym(Builder $query, Gym|int $gym): Builder
    {
        $gymId = $gym instanceof Gym ? $gym->id : $gym;

        return $query->where(fn (Builder $inner) => $inner
            ->whereNull('statutory_rates.gym_id')
            ->orWhere('statutory_rates.gym_id', $gymId));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
