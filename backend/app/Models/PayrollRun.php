<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-61: one pay period, its approval chain and its cached totals. */
#[Fillable([
    'starts_on', 'ends_on', 'status', 'steps', 'current_step', 'round',
    'prepared_by', 'submitted_by', 'submitted_at', 'computed_at', 'rejection_reason',
    'released_by', 'released_at', 'self_released',
    'gross_total', 'employee_statutory_total', 'tax_total', 'net_total', 'employer_statutory_total',
])]
class PayrollRun extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => PayrollStatus::class,
            'steps' => 'array',
            'current_step' => 'integer',
            'round' => 'integer',
            'submitted_at' => 'datetime',
            'computed_at' => 'datetime',
            'released_at' => 'datetime',
            'self_released' => 'boolean',
            'gross_total' => 'decimal:2',
            'employee_statutory_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'employer_statutory_total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class)->orderBy('name');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PayrollApproval::class)->orderBy('round')->orderBy('step_index')->orderBy('id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isDraft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    public function isReleased(): bool
    {
        return $this->status === PayrollStatus::Released;
    }
}
