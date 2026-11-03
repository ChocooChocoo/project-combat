<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'purpose', 'code_hash', 'expires_at'])]
class OneTimeCode extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
