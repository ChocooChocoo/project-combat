<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-61: one approval or rejection. Rows are append-only history per round. */
#[Fillable(['payroll_run_id', 'round', 'step_index', 'step_key', 'user_id', 'decision', 'reason', 'decided_at'])]
class PayrollApproval extends Model
{
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'step_index' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id')->withoutGlobalScope('gym');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
