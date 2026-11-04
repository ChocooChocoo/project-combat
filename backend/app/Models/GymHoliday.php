<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Database\Factories\GymHolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A holiday the gym adds on top of the national list (R-27). */
#[Fillable(['date', 'name'])]
class GymHoliday extends Model
{
    /** @use HasFactory<GymHolidayFactory> */
    use BelongsToGym, HasFactory;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
