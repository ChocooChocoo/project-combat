<?php

namespace App\Http\Controllers;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use App\Notifications\GymNotice;
use App\Support\CurrentGym;
use App\Support\GymRoleAssigner;
use App\Support\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R-138: members apply to a job posting with experience, credentials and a valid ID. Approval adds them as an
 * employee with the posting's title and starter role. The account type stays member.
 */
class JobApplicationController extends Controller
{
    /** Member: apply to an open posting at an approved gym. */
    public function store(Request $request, int $gym, int $posting): JsonResponse
    {
        $user = $request->user();
        $gym = Gym::where('status', GymStatus::Approved->value)->findOrFail($gym);
        app(CurrentGym::class)->set($gym);
        $job = JobPosting::where('status', 'open')->findOrFail($posting);

        abort_if($user->is_platform_admin, 422, 'Platform administrators cannot apply for jobs.');

        if ($user->hasGymRole($gym, GymPersonType::Owner, GymPersonType::Employee)) {
            throw ValidationException::withMessages(['experience' => 'You already work at this gym.']);
        }

        if (JobApplication::where('job_posting_id', $job->id)->where('user_id', $user->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['experience' => 'You already have a waiting application for this job.']);
        }

        $data = $request->validate([
            'experience' => ['required', 'string', 'max:5000'],
            'credentials' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'valid_id' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $application = new JobApplication(['experience' => $data['experience']]);
        $application->user_id = $user->id;
        $application->job_posting_id = $job->id;
        $application->status = 'pending';
        foreach (['credentials', 'valid_id'] as $kind) {
            $file = $request->file($kind);
            $application->{"{$kind}_path"} = $file->store("job-applications/{$gym->id}", 'local');
            $application->{"{$kind}_name"} = $file->getClientOriginalName();
        }
        $application->save();

        $gym->peopleWithPermission('hr.job_applications.approve')->each(fn (User $person) => $person->notify(new GymNotice(
            'New job application',
            "{$user->fullName()} applied for {$job->title}.",
            $gym->id,
            "/gyms/{$gym->id}/manage/hiring",
        )));

        return response()->json(['application' => $this->payload($application->load(['user', 'posting']))], 201);
    }

    /** Staff: applications at this gym, waiting first. */
    public function index(Request $request, Gym $gym): JsonResponse
    {
        $request->validate(Paging::rules());
        [$rows, $meta] = Paging::rows(JobApplication::with(['user', 'posting', 'decidedBy'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderByDesc('id'), $request);

        return response()->json(['applications' => $rows->map(fn (JobApplication $row) => $this->payload($row))->values(), ...($meta ? ['meta' => $meta] : [])]);
    }

    public function file(Gym $gym, int $application, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['credentials', 'valid_id'], true), 404);
        $row = JobApplication::findOrFail($application);

        return Storage::disk('local')->response($row->{"{$kind}_path"}, $row->{"{$kind}_name"});
    }

    public function decide(Request $request, Gym $gym, int $application): JsonResponse
    {
        $row = JobApplication::findOrFail($application);
        $actor = $request->user();
        abort_if($row->user_id === $actor->id, 403, 'You cannot decide your own application.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ], ['reason.required_if' => 'Give the applicant a reason.']);

        DB::transaction(function () use ($gym, $row, $actor, $data) {
            $locked = JobApplication::with(['user', 'posting'])->lockForUpdate()->findOrFail($row->id);
            abort_unless($locked->status === 'pending', 409, 'Only waiting applications can be decided.');
            $user = $locked->user;
            $posting = $locked->posting;

            if ($data['decision'] === 'approve') {
                if (! $user->hasGymRole($gym, GymPersonType::Employee)) {
                    $gym->addPerson($user, GymPersonType::Employee);
                }

                $profile = EmployeeProfile::where('user_id', $user->id)->first() ?? new EmployeeProfile;
                $profile->user_id = $user->id;
                $profile->fill(['job_title' => $posting->title, 'started_on' => today()->toDateString(), 'ended_on' => null])->save();

                if ($posting->gym_role_id) {
                    app(GymRoleAssigner::class)->replace($actor, $gym, $user, array_values(array_unique([
                        ...$user->gymRoles($gym)->pluck('id')->all(), $posting->gym_role_id,
                    ])));
                }
            }

            $locked->status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
            $locked->decided_by = $actor->id;
            $locked->decided_at = now();
            $locked->decision_reason = $data['reason'] ?? null;
            $locked->save();

            $user->notify(new GymNotice(
                $locked->status === 'approved' ? 'Job application approved' : 'Job application not approved',
                $locked->status === 'approved'
                    ? "You are now {$posting->title} at {$gym->name}. Open My work to see your details."
                    : "{$gym->name} did not approve your application for {$posting->title}. Reason: {$locked->decision_reason}",
                $gym->id,
                $locked->status === 'approved' ? "/gyms/{$gym->id}/manage/my-work" : "/gyms/{$gym->id}",
            ));
        });

        return response()->json(['application' => $this->payload(JobApplication::with(['user', 'posting', 'decidedBy'])->findOrFail($row->id))]);
    }

    private function payload(JobApplication $row): array
    {
        return [
            'id' => $row->id,
            'posting' => ['id' => $row->job_posting_id, 'title' => $row->posting?->title],
            'applicant' => ['id' => $row->user_id, 'name' => $row->user?->fullName(), 'email' => $row->user?->email],
            'status' => $row->status,
            'experience' => $row->experience,
            'credentials_name' => $row->credentials_name,
            'valid_id_name' => $row->valid_id_name,
            'decided_by' => $row->decidedBy?->fullName(),
            'decided_at' => $row->decided_at?->toIso8601String(),
            'decision_reason' => $row->decision_reason,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
