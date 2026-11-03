<?php

namespace App\Models\Concerns;

use App\Models\Gym;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One shared rule for gym separation (R-09).
 *
 * Every query on a model using this trait is limited to the current gym.
 * With no current gym, queries return nothing (fail closed). Code that truly
 * needs every gym, such as platform-wide counts, must say so with
 * withoutGlobalScope('gym').
 */
trait BelongsToGym
{
    public static function bootBelongsToGym(): void
    {
        static::addGlobalScope('gym', function (Builder $query) {
            $gymId = app(CurrentGym::class)->id();
            $column = $query->getModel()->qualifyColumn('gym_id');

            $gymId === null ? $query->whereRaw('1 = 0') : $query->where($column, $gymId);
        });

        static::creating(function ($model) {
            $gymId = app(CurrentGym::class)->id();

            if ($gymId === null) {
                throw new LogicException('Gym records can only be created inside a gym.');
            }

            if ($model->gym_id !== null && (int) $model->gym_id !== $gymId) {
                throw new LogicException('A record cannot be created for another gym.');
            }

            $model->gym_id = $gymId;
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
