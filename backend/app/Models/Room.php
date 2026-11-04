<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A training area sessions can use (R-21). Retired rooms stay for history. */
#[Fillable(['name', 'is_active'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use BelongsToGym, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
