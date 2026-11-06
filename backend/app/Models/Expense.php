<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded cost (R-64). Never edited after it is saved: a correction is its
 * own negative entry pointing at the original.
 */
#[Fillable([
    'category', 'amount', 'incurred_on', 'method', 'note', 'source_type', 'source_id',
    'corrects_id', 'recorded_by',
])]
class Expense extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Expenses cannot be changed. Record a correction instead.'));
        static::deleting(fn () => throw new \LogicException('Expenses cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
            'source_id' => 'integer',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'corrects_id')->withoutGlobalScope('gym');
    }
}
