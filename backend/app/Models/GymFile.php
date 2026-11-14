<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** A gym gallery photo or policy PDF (R-134, R-135). The stored path stays private. */
#[Fillable(['kind', 'path', 'original_name', 'size', 'sort'])]
#[Hidden(['path'])]
class GymFile extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['size' => 'integer', 'sort' => 'integer'];
    }
}
