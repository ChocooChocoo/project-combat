<?php

namespace App\Models;

use App\Enums\CustomerInteractionChannel;
use App\Enums\CustomerInteractionKind;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One item in the customer history (R-70). Append-only, apart from the
 * resolution fields of a concern.
 */
#[Fillable([
    'customer_id', 'kind', 'channel', 'body', 'occurred_at', 'recorded_by',
    'resolved_at', 'resolved_by', 'resolution',
])]
class CustomerInteraction extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        static::updating(function (CustomerInteraction $interaction) {
            $resolutionFields = ['resolved_at', 'resolved_by', 'resolution'];
            $changed = array_keys($interaction->getDirty());

            if (array_diff($changed, $resolutionFields) !== []) {
                throw new LogicException('Interactions are append-only. Add a new entry instead.');
            }

            if ($interaction->getRawOriginal('kind') !== CustomerInteractionKind::Concern->value) {
                throw new LogicException('Only concerns have a resolution.');
            }
        });

        static::deleting(fn () => throw new LogicException('Interactions cannot be deleted. Add a correcting entry instead.'));
    }

    protected function casts(): array
    {
        return [
            'kind' => CustomerInteractionKind::class,
            'channel' => CustomerInteractionChannel::class,
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isConcern(): bool
    {
        return $this->kind === CustomerInteractionKind::Concern;
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
