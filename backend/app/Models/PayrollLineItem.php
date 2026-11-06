<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-59: one posted step in a line's working-out, signed and rounded individually. */
#[Fillable(['kind', 'label', 'quantity', 'rate', 'amount', 'ref'])]
class PayrollLineItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'ref' => 'array',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class, 'payroll_line_id');
    }
}
