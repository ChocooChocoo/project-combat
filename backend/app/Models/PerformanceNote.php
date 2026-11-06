<?php

namespace App\Models;

use App\Enums\PerformanceNoteKind;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dated note about one employee's work (R-58). Append-only; never shown to the subject. */
#[Fillable(['user_id', 'author_id', 'noted_on', 'kind', 'body'])]
class PerformanceNote extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Performance notes cannot be changed. Add a new note instead.'));
        static::deleting(fn () => throw new \LogicException('Performance notes cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'noted_on' => 'date',
            'kind' => PerformanceNoteKind::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
