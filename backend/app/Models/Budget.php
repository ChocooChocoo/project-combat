<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-67: one planned amount per gym, kind, category and month. */
#[Fillable(['kind', 'category', 'month', 'amount', 'updated_by'])]
class Budget extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        // Keep the month key as a plain date on SQLite too, where a date column
        // does not truncate the time Eloquent would otherwise write.
        static::saving(function (Budget $budget) {
            if ($budget->month !== null) {
                $budget->attributes['month'] = $budget->month->copy()->startOfMonth()->toDateString();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
