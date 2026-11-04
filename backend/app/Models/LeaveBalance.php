<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Database\Factories\LeaveBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A year's allowance for one leave type (R-26). */
#[Fillable(['user_id', 'type', 'year', 'allowance'])]
class LeaveBalance extends Model
{
    /** @use HasFactory<LeaveBalanceFactory> */
    use BelongsToGym, HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allowance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
