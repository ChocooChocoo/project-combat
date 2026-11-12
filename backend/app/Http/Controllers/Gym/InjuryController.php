<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Injury;
use App\Models\InjuryUpdate;
use App\Models\User;
use App\Notifications\GymNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * R-119: injuries. Details need health_records.records.view (or being the athlete); writing needs
 * health_records.injuries.manage. The athlete can report and read their own. Coaches without health
 * access see only the restricted flag on lists and rosters.
 */
class InjuryController extends Controller
{
    public function index(Request $request, Gym $gym, User $user): JsonResponse
    {
        $viewer = $request->user();
        $self = $viewer->id === $user->id;
        abort_unless($self || $viewer->hasPermission($gym, 'health_records.records.view'), 403, 'Injury details are health records.');

        return response()->json(['injuries' => $this->injuries($user->id)]);
    }

    public function store(Request $request, Gym $gym, User $user): JsonResponse
    {
        $viewer = $request->user();
        $self = $viewer->id === $user->id;
        abort_unless($self || $viewer->hasPermission($gym, 'health_records.injuries.manage'), 403);
        abort_unless($user->hasGymRole($gym, GymPersonType::Member), 404);

        $data = $request->validate([
            'body_part' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'severity' => ['required', Rule::in(['minor', 'moderate', 'severe'])],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'restrictions' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($user, $viewer, $data) {
            $injury = new Injury($data);
            $injury->user_id = $user->id;
            $injury->status = 'active';
            $injury->reported_by = $viewer->id;
            $injury->save();
            $this->log($injury, 'active', $viewer, 'Reported');
        });

        if ($self) {
            $name = trim("{$user->first_name} {$user->last_name}");
            $gym->peopleWithPermission('health_records.injuries.manage')
                ->reject(fn (User $person) => $person->id === $user->id)
                ->each(fn (User $person) => $person->notify(new GymNotice(
                    'Injury reported',
                    "{$name} reported a {$data['severity']} {$data['body_part']} injury.",
                    $gym->id,
                    "/gyms/{$gym->id}/manage/members",
                )));
        }

        return response()->json(['injuries' => $this->injuries($user->id)], 201);
    }

    /** Recovery log step: new status (active, recovering, cleared), note and restrictions. */
    public function update(Request $request, Gym $gym, int $injury): JsonResponse
    {
        $model = Injury::with('user')->findOrFail($injury);
        $viewer = $request->user();
        abort_unless($viewer->hasPermission($gym, 'health_records.injuries.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'recovering', 'cleared'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'restrictions' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $wasOpen = in_array($model->status, Injury::OPEN, true);

        DB::transaction(function () use ($model, $viewer, $data) {
            $model->status = $data['status'];
            $model->cleared_on = $data['status'] === 'cleared' ? today() : null;
            if (array_key_exists('restrictions', $data)) {
                $model->restrictions = $data['restrictions'];
            }
            $model->save();
            $this->log($model, $data['status'], $viewer, $data['note'] ?? null);
        });

        if ($wasOpen && $data['status'] === 'cleared') {
            $model->user->notify(new GymNotice(
                'Cleared to train',
                "Your {$model->body_part} injury is marked cleared at {$gym->name}.",
                $gym->id,
                "/gyms/{$gym->id}/my-record",
            ));
        }

        return response()->json(['injuries' => $this->injuries($model->user_id)]);
    }

    private function log(Injury $injury, string $status, User $by, ?string $note): void
    {
        $update = new InjuryUpdate;
        $update->forceFill(['injury_id' => $injury->id, 'status' => $status, 'note' => $note, 'recorded_by' => $by->id])->save();
    }

    private function injuries(int $userId): array
    {
        $name = fn (?User $user) => $user ? trim("{$user->first_name} {$user->last_name}") : null;

        return Injury::with(['reportedBy', 'updates.recordedBy'])->where('user_id', $userId)
            ->orderByRaw("case when status = 'cleared' then 1 else 0 end")->orderByDesc('occurred_on')->orderByDesc('id')->get()
            ->map(fn (Injury $injury) => [
                'id' => $injury->id,
                'body_part' => $injury->body_part,
                'description' => $injury->description,
                'severity' => $injury->severity,
                'occurred_on' => $injury->occurred_on?->toDateString(),
                'status' => $injury->status,
                'restrictions' => $injury->restrictions,
                'cleared_on' => $injury->cleared_on?->toDateString(),
                'reported_by' => $name($injury->reportedBy),
                'updates' => $injury->updates->map(fn (InjuryUpdate $update) => [
                    'id' => $update->id, 'status' => $update->status, 'note' => $update->note,
                    'by' => $name($update->recordedBy), 'at' => $update->created_at?->toIso8601String(),
                ])->values(),
            ])->values()->all();
    }
}
