<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipReminder extends Model
{
    protected function casts(): array
    {
        return ['ends_on' => 'date', 'days_before' => 'integer'];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
