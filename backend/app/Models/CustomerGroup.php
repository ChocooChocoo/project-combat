<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A named audience of customers for later announcements (R-71, R-80). */
#[Fillable(['name', 'description'])]
class CustomerGroup extends Model
{
    use BelongsToGym;

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_group_members')->withTimestamps();
    }
}
