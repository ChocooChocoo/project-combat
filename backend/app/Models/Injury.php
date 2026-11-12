<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-119: one injury of an athlete, with its recovery log. Details are health records. */
#[Fillable(['body_part', 'description', 'severity', 'occurred_on', 'restrictions'])]
class Injury extends Model
{
    use BelongsToGym;

    public const OPEN = ['active', 'recovering'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'cleared_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(InjuryUpdate::class)->orderByDesc('id');
    }

    /**
     * Users among $userIds with an active or recovering injury in the gym: the "restricted" flag coaches see.
     *
     * @param  iterable<int>  $userIds
     * @return array<int, true>
     */
    public static function restrictedAmong(int $gymId, iterable $userIds): array
    {
        return static::withoutGlobalScope('gym')->where('gym_id', $gymId)->whereIn('status', self::OPEN)
            ->whereIn('user_id', collect($userIds)->all())->pluck('user_id')->unique()
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }
}
