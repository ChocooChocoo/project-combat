<?php

namespace App\Support\PlatformNotices;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Enums\PlatformNoticeAudience as AudienceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PlatformNoticeAudience
{
    public function resolve(AudienceType $audience, User $sender): Collection
    {
        $query = User::where('id', '!=', $sender->id);
        if ($audience === AudienceType::Owners) {
            $query->whereHas('gyms', fn ($gym) => $gym->where('gyms.status', GymStatus::Approved)
                ->where('gym_user.role', GymPersonType::Owner));
        } else {
            $query->whereNotNull('email_verified_at');
        }

        return $query->orderBy('id')->get();
    }
}
