<?php

namespace App\Http\Controllers;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Models\CoachApplication;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\GymNotice;
use App\Support\CoachScheduleConflicts;
use App\Support\CurrentGym;
use App\Support\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * R-117: members apply to coach at a gym. Hours that overlap their shifts at another gym block the
 * application. Approval adds them as an employee with a coach profile, schedule and the Coach starter role.
 * The account type stays member.
 */
class CoachApplicationController extends Controller
{
    /** Member: apply to coach at an approved gym. */
    public function store(Request $request, int $gym): JsonResponse
    {
        $user = $request->user();
        $gym = Gym::where('status', GymStatus::Approved->value)->findOrFail($gym);
        app(CurrentGym::class)->set($gym);

        abort_if($user->is_platform_admin, 422, 'Platform administrators cannot apply to coach.');

        if ($user->hasGymRole($gym, GymPersonType::Owner, GymPersonType::Employee)) {
            throw ValidationException::withMessages(['experience' => 'You already work at this gym.']);
        }

        if (CoachApplication::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['experience' => 'You already have a waiting application at this gym.']);
        }

        $data = $request->validate([
            'experience' => ['required', 'string', 'max:5000'],
            'rank' => ['nullable', 'string', 'max:100'],
            'specialisation' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'availability' => ['required', 'array', 'min:1', 'max:7'],
            'availability.*.weekday' => ['required', 'distinct', Rule::in(array_keys(config('hr.weekdays')))],
            'availability.*.start' => ['required', 'date_format:H:i'],
            'availability.*.end' => ['required', 'date_format:H:i', 'after:availability.*.start'],
        ], [
            'availability.required' => 'Add at least one day you can coach.',
            'availability.*.weekday.distinct' => 'Add each day once.',
            'availability.*.end.after' => 'The end time must be after the start time.',
        ]);

        $availability = $this->slots($data['availability']);
        CoachScheduleConflicts::assertNone($user, $gym, $availability);

        $application = new CoachApplication([...$data, 'availability' => $availability]);
        $application->user_id = $user->id;
        $application->status = 'pending';
        $application->save();

        $gym->peopleWithPermission('hr.coach_applications.approve')->each(fn (User $person) => $person->notify(new GymNotice(
            'New coach application',
            trim("{$user->first_name} {$user->last_name}")." applied to coach at {$gym->name}.",
            $gym->id,
            "/gyms/{$gym->id}/manage/coach-applications",
        )));

        return response()->json(['application' => $this->payload($application->load('user'), $gym)], 201);
    }

    /** Member: their own applications at every gym. */
    public function mine(Request $request): JsonResponse
    {
        $rows = CoachApplication::withoutGlobalScope('gym')->with(['gym:id,name', 'user'])
            ->where('user_id', $request->user()->id)->orderByDesc('id')->get();

        return response()->json(['applications' => $rows->map(fn (CoachApplication $row) => $this->payload($row, $row->gym))->values()]);
    }

    public function withdraw(Request $request, int $application): JsonResponse
    {
        $row = CoachApplication::withoutGlobalScope('gym')->with(['gym:id,name', 'user'])
            ->where('user_id', $request->user()->id)->findOrFail($application);
        abort_unless($row->status === 'pending', 409, 'Only a waiting application can be withdrawn.');
        $row->status = 'withdrawn';
        $row->save();

        return response()->json(['application' => $this->payload($row, $row->gym)]);
    }

    /** Staff: applications at this gym, waiting first, each with a fresh conflict check. */
    public function index(Request $request, Gym $gym): JsonResponse
    {
        $request->validate(Paging::rules());
        [$rows, $meta] = Paging::rows(CoachApplication::with(['user', 'decidedBy'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderByDesc('id'), $request);

        return response()->json(['applications' => $rows->map(fn (CoachApplication $row) => $this->payload($row, $gym, true))->values(), ...($meta ? ['meta' => $meta] : [])]);
    }

    public function decide(Request $request, Gym $gym, int $application): JsonResponse
    {
        $row = CoachApplication::with('user')->findOrFail($application);
        $actor = $request->user();
        abort_if($row->user_id === $actor->id, 403, 'You cannot decide your own application.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ], ['reason.required_if' => 'Give the applicant a reason.']);

        DB::transaction(function () use ($gym, $row, $actor, $data) {
            $locked = CoachApplication::with('user')->lockForUpdate()->findOrFail($row->id);
            abort_unless($locked->status === 'pending', 409, 'Only waiting applications can be decided.');
            $user = $locked->user;

            if ($data['decision'] === 'approve') {
                // Schedules may have changed since applying.
                CoachScheduleConflicts::assertNone($user, $gym, $locked->availability);

                if (! $user->hasGymRole($gym, GymPersonType::Employee)) {
                    $gym->addPerson($user, GymPersonType::Employee);
                }

                $profile = EmployeeProfile::where('user_id', $user->id)->first() ?? new EmployeeProfile;
                $profile->user_id = $user->id;
                $profile->fill([
                    'job_title' => $profile->job_title ?: 'Coach',
                    'started_on' => $profile->started_on ?? today()->toDateString(),
                    'ended_on' => null,
                    'coach_experience' => $locked->experience,
                    'coach_rank' => $locked->rank,
                    'coach_specialisation' => $locked->specialisation,
                    'coach_bio' => $locked->bio,
                ])->save();

                foreach ($locked->availability as $slot) {
                    WorkSchedule::updateOrCreate(
                        ['user_id' => $user->id, 'weekday' => $slot['weekday']],
                        ['start_time' => $slot['start'], 'end_time' => $slot['end']],
                    );
                }

                if ($role = GymRole::where('template_key', 'coach')->where('status', 'active')->first()) {
                    $role->users()->syncWithoutDetaching([$user->id]);
                }
            }

            $locked->status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
            $locked->decided_by = $actor->id;
            $locked->decided_at = now();
            $locked->decision_reason = $data['reason'] ?? null;
            $locked->save();

            $user->notify(new GymNotice(
                $locked->status === 'approved' ? 'Coach application approved' : 'Coach application not approved',
                $locked->status === 'approved'
                    ? "You are now a coach at {$gym->name}. Open My work to see your schedule."
                    : "{$gym->name} did not approve your coach application. Reason: {$locked->decision_reason}",
                $gym->id,
                $locked->status === 'approved' ? "/gyms/{$gym->id}/manage/my-work" : '/coach-applications',
            ));
        });

        return response()->json(['application' => $this->payload(CoachApplication::with(['user', 'decidedBy'])->findOrFail($row->id), $gym, true)]);
    }

    /** Keeps weekday, start and end only, as plain H:i strings. */
    private function slots(array $availability): array
    {
        return array_values(array_map(fn ($slot) => [
            'weekday' => $slot['weekday'], 'start' => $slot['start'], 'end' => $slot['end'],
        ], $availability));
    }

    private function payload(CoachApplication $row, ?Gym $gym, bool $withConflicts = false): array
    {
        $name = fn (?User $user) => $user ? trim("{$user->first_name} {$user->last_name}") : null;

        return [
            'id' => $row->id,
            'gym' => $gym ? ['id' => $gym->id, 'name' => $gym->name] : null,
            'applicant' => ['id' => $row->user_id, 'name' => $name($row->user), 'email' => $row->user?->email],
            'status' => $row->status,
            'experience' => $row->experience,
            'rank' => $row->rank,
            'specialisation' => $row->specialisation,
            'bio' => $row->bio,
            'availability' => $row->availability,
            'conflicts' => $withConflicts && $row->status === 'pending' && $gym && $row->user
                ? CoachScheduleConflicts::find($row->user, $gym, $row->availability)
                : [],
            'decided_by' => $name($row->decidedBy),
            'decided_at' => $row->decided_at?->toIso8601String(),
            'decision_reason' => $row->decision_reason,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
