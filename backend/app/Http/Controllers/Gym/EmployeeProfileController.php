<?php

namespace App\Http\Controllers\Gym;

use App\Enums\AccessLevel;
use App\Enums\GymPersonType;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** R-23: self-service coach profile, plus employment details for HR. */
class EmployeeProfileController extends Controller
{
    use PresentsHr;

    public function update(Request $request, Gym $gym, User $user): JsonResponse
    {
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 404);

        $viewer = $request->user();
        $isSelf = $viewer->is($user);
        $hrManage = $viewer->canInGym($gym, Module::Hr, AccessLevel::Manage);

        abort_unless($isSelf || $hrManage, 403);

        $rules = [
            'coach_experience' => ['nullable', 'string', 'max:5000'],
            'coach_rank' => ['nullable', 'string', 'max:100'],
            'coach_specialisation' => ['nullable', 'string', 'max:150'],
            'coach_bio' => ['nullable', 'string', 'max:5000'],
            'coach_public' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];

        if ($hrManage) {
            $rules += [
                'job_title' => ['nullable', 'string', 'max:100'],
                'started_on' => ['nullable', 'date'],
            ];

            if ($viewer->canSeePay($gym)) {
                $rules += [
                    'pay_basis' => ['nullable', Rule::in(array_keys(config('hr.pay_bases')))],
                    'monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                    'per_session_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                ];
            }
        }

        $data = $request->validate($rules);
        $profile = $this->editableProfile($gym, $user);

        if ($request->hasFile('photo')) {
            if ($profile->coach_photo_path) {
                Storage::disk('public')->delete($profile->coach_photo_path);
            }

            $profile->coach_photo_path = $request->file('photo')->store("coach-photos/{$gym->id}", 'public');
        }

        $profile->fill(collect($data)->except('photo')->all())->save();

        return response()->json([
            'employee' => $this->employeePayload($gym, $user, $viewer->canSeePay($gym)),
        ]);
    }
}
