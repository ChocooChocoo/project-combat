<?php

namespace App\Support\Announcements;

use App\Enums\AnnouncementAudience as AudienceType;
use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Enums\SessionStatus;
use App\Models\CustomerGroup;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AnnouncementAudience
{
    /** @return array{users: \Illuminate\Support\Collection<int, User>, skipped: int} */
    public function resolve(Gym $gym, AudienceType $type, ?int $sessionId, ?int $groupId): array
    {
        if ($type === AudienceType::AllMembers) {
            $ids = Membership::where('gym_id', $gym->id)
                ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Frozen])
                ->whereNotNull('user_id')->select('user_id');

            return ['users' => User::whereIn('id', $ids)->get(), 'skipped' => 0];
        }

        if ($type === AudienceType::Session) {
            $session = TrainingSession::where('gym_id', $gym->id)->lockForUpdate()->findOrFail($sessionId);
            if ($session->status !== SessionStatus::Scheduled || $session->ends_at->lt(now())) {
                throw ValidationException::withMessages(['training_session_id' => 'Choose a scheduled session that has not ended.']);
            }
            $ids = $session->bookings()->where('status', BookingStatus::Booked)->select('user_id');

            return ['users' => User::whereIn('id', $ids)->get(), 'skipped' => 0];
        }

        $group = CustomerGroup::where('gym_id', $gym->id)->lockForUpdate()->findOrFail($groupId);
        $customers = $group->members()->get(['customers.id', 'customers.user_id']);

        return [
            'users' => User::whereIn('id', $customers->pluck('user_id')->filter()->unique())->get(),
            'skipped' => $customers->whereNull('user_id')->count(),
        ];
    }
}
