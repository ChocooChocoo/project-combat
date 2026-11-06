<?php

namespace App\Models;

use App\Enums\CustomerFollowUpStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** A scheduled contact with a customer, owned by one staff member (R-69). */
#[Fillable([
    'customer_id', 'assigned_to', 'due_at', 'note', 'status', 'done_at', 'done_by', 'outcome', 'reminded_at',
])]
class CustomerFollowUp extends Model
{
    use BelongsToGym;

    protected $attributes = ['status' => 'open'];

    protected static function booted(): void
    {
        static::updating(function (CustomerFollowUp $followUp) {
            if (! $followUp->isDirty('status')) {
                return;
            }

            $from = $followUp->getRawOriginal('status');
            $to = $followUp->status instanceof CustomerFollowUpStatus ? $followUp->status->value : $followUp->status;

            if ($from !== CustomerFollowUpStatus::Open->value
                || ! in_array($to, [CustomerFollowUpStatus::Done->value, CustomerFollowUpStatus::Cancelled->value], true)) {
                throw new LogicException('A follow-up moves from open to done or cancelled only.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'status' => CustomerFollowUpStatus::class,
            'done_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isOpen(): bool
    {
        return $this->status === CustomerFollowUpStatus::Open;
    }
}
