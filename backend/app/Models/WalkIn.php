<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-137: one walk-in visit. Walk-ins have no user, so they are not in attendance analytics. */
#[Fillable(['customer_id', 'name', 'phone', 'email', 'health_answers', 'health_notes', 'waivers', 'signed_name', 'checked_in_at'])]
class WalkIn extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['health_answers' => 'array', 'waivers' => 'array', 'checked_in_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
