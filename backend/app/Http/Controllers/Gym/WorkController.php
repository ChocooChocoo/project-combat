<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The employee's own work view: profile, schedule, leave and holidays. */
class WorkController extends Controller
{
    use PresentsHr;

    public function show(Request $request, Gym $gym): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 404);

        $year = now()->year;

        $requests = $gym->leaveRequests()
            ->with(['user', 'decidedBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LeaveRequest $leave) => $this->leavePayload($gym, $leave))
            ->values();

        return response()->json([
            'work' => [
                'profile' => $this->profilePayload($this->profileFor($gym, $user), $user->canViewCompensation($gym)),
                'schedule' => $this->scheduleWeek($gym, $user),
                'balances' => $this->leaveBalanceTypes($gym, $user, $year),
                'requests' => $requests,
                'approved_leave_dates' => $this->approvedLeaveDates($gym, $user, $year),
                'holidays' => $this->holidaysFor($gym, $year),
                'is_coach' => $this->coaches($gym)->contains('id', $user->id),
            ],
        ]);
    }
}
