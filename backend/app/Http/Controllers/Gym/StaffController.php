<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\EmployeeAdded;
use App\Support\GymRoleAssigner;
use App\Support\OneTimeCodes;
use App\Support\SessionFlagger;
use App\Support\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-22 and R-23: adding, editing, showing and removing employees. */
class StaffController extends Controller
{
    use PresentsHr;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $request->validate(Paging::rules());
        $canViewCompensation = $request->user()->canViewCompensation($gym);
        $canViewGovIds = $request->user()->canViewGovernmentIds($gym);
        $owner = $gym->people()
            ->wherePivot('role', GymPersonType::Owner->value)
            ->orderBy('id')
            ->first();

        [$rows, $meta] = Paging::rows($gym->people()
            ->wherePivot('role', GymPersonType::Employee->value)
            ->orderBy('first_name')
            ->orderBy('last_name'), $request);
        $staff = $rows
            ->map(fn (User $user) => $this->employeePayload($gym, $user, $canViewCompensation, $canViewGovIds))
            ->values();

        return response()->json([
            'staff' => $staff,
            'owner' => $owner ? [
                'id' => $owner->id,
                'first_name' => $owner->first_name,
                'last_name' => $owner->last_name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'photo_url' => $owner->photo_path ? Storage::disk('public')->url($owner->photo_path) : null,
            ] : null,
            ...($meta ? ['meta' => $meta] : []),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        if ($request->exists('role_ids')) {
            abort_unless($request->user()->hasPermission($gym, 'hr.roles.manage'), 403);
        }
        $existing = User::where('email', $request->input('email'))->first();

        if ($existing && ($existing->is_platform_admin || $existing->hasGymRole($gym, GymPersonType::Owner))) {
            throw ValidationException::withMessages([
                'email' => 'This person cannot be added as an employee.',
            ]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'first_name' => [$existing ? 'sometimes' : 'required', 'string', 'max:100'],
            'last_name' => [$existing ? 'sometimes' : 'required', 'string', 'max:100'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'distinct'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'started_on' => ['nullable', 'date'],
            'pay_basis' => ['nullable', Rule::in(array_keys(config('hr.pay_bases')))],
            'monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'per_session_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'coach_experience' => ['nullable', 'string', 'max:5000'],
            'coach_rank' => ['nullable', 'string', 'max:100'],
            'coach_specialisation' => ['nullable', 'string', 'max:150'],
            'coach_bio' => ['nullable', 'string', 'max:5000'],
            'coach_public' => ['nullable', 'boolean'],
        ]);

        $user = DB::transaction(function () use ($request, $gym, $data, $existing) {
            if ($existing) {
                $user = $existing;
            } else {
                $user = User::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'password' => Str::password(32),
                ]);
                $user->forceFill([
                    'password_change_required' => true,
                    'email_verified_at' => null,
                ])->save();
            }

            if (! $user->hasGymRole($gym, GymPersonType::Employee)) {
                $gym->addPerson($user, GymPersonType::Employee);
            }

            if (array_key_exists('role_ids', $data)) {
                app(GymRoleAssigner::class)->replace($request->user(), $gym, $user, $data['role_ids'] ?? []);
            }

            $profile = $this->editableProfile($gym, $user);
            $profile->fill(collect($data)->only([
                'job_title', 'started_on', 'pay_basis', 'monthly_rate', 'per_session_rate',
                'coach_experience', 'coach_rank', 'coach_specialisation', 'coach_bio', 'coach_public',
            ])->all())->save();

            if ($existing) {
                $user->notify(new EmployeeAdded($gym));
            } else {
                app(OneTimeCodes::class)->invite($user, $gym);
            }

            return $user;
        });

        return response()->json([
            'employee' => $this->employeePayload($gym, $user->fresh(), $request->user()->canViewCompensation($gym), $request->user()->canViewGovernmentIds($gym)),
        ], 201);
    }

    public function show(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->ensureEmployee($gym, $user);
        $this->ensureCanView($request->user(), $gym, $user);

        $viewer = $request->user();
        $employee = $this->employeePayload(
            $gym, $user, $viewer->canViewCompensation($gym), $viewer->canViewGovernmentIds($gym) || $viewer->is($user),
        );
        $year = now()->year;
        $employee['balances'] = $this->leaveBalanceTypes($gym, $user, $year);
        $employee['approved_leave_dates'] = $this->approvedLeaveDates($gym, $user, $year);

        return response()->json(['employee' => $employee]);
    }

    /** HR manage: job title, start date and pay. Roles use the roles endpoint (R-08). */
    public function update(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->ensureEmployee($gym, $user);

        $rules = [
            'job_title' => ['nullable', 'string', 'max:100'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date'],
            'employment_type' => ['nullable', Rule::in(array_keys(config('hr.employment_types')))],
        ];

        if ($request->user()->canViewCompensation($gym)) {
            $rules += [
                'pay_basis' => ['nullable', Rule::in(array_keys(config('hr.pay_bases')))],
                'monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'per_session_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            ];
        }

        // R-121: the route needs hr.staff.manage, so the ID fields are always allowed here.
        $this->cleanGovernmentIds($request);
        $data = $request->validate($rules + $this->governmentIdRules(), $this->governmentIdMessages());

        $profile = $this->editableProfile($gym, $user);
        $profile->fill($data)->save();

        $viewer = $request->user();

        return response()->json([
            'employee' => $this->employeePayload(
                $gym, $user, $viewer->canViewCompensation($gym), $viewer->canViewGovernmentIds($gym) || $viewer->is($user),
            ),
        ]);
    }

    /** Removing an employee ends their gym access immediately; their history stays (R-22). */
    public function destroy(Request $request, Gym $gym, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 403, 'You cannot remove yourself.');
        abort_if($user->hasGymRole($gym, GymPersonType::Owner), 403, 'The owner cannot be removed.');

        $this->ensureEmployee($gym, $user);

        DB::transaction(function () use ($request, $gym, $user) {
            // R-46: same lock order as session saves, so no occurrence is missed.
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            if ($user->gymRoles($gym)->exists()) {
                abort_unless($request->user()->hasPermission($gym, 'hr.roles.manage'), 403);
                app(GymRoleAssigner::class)->replace($request->user(), $gym, $user, []);
            }

            // R-59: the profile stays for history, with the last day of work recorded.
            $profile = $gym->employees()->where('user_id', $user->id)->first();
            if ($profile && ! $profile->ended_on) {
                $profile->update(['ended_on' => now()->toDateString()]);
            }

            $gym->people()->wherePivot('role', GymPersonType::Employee->value)->detach($user->id);

            // D-32: the removed coach's upcoming sessions need a new coach.
            $name = trim("{$user->first_name} {$user->last_name}");
            app(SessionFlagger::class)->flag($gym, TrainingSession::where('coach_id', $user->id),
                "Coach {$name} is no longer on staff", "{$name} was removed from staff");
        });

        return response()->json(['message' => 'Employee removed.']);
    }

    private function ensureEmployee(Gym $gym, User $user): void
    {
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 404);
    }

    private function ensureCanView(User $viewer, Gym $gym, User $employee): void
    {
        abort_unless(
            $viewer->is($employee) || $viewer->hasPermission($gym, 'hr.staff.view'),
            403,
        );
    }
}
