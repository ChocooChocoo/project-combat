<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

class GymMemberReview extends Model
{
    use BelongsToGym;
}
