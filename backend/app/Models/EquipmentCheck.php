<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-120: one safety inspection of an equipment or protective gear item. */
class EquipmentCheck extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['checked_on' => 'date'];
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
