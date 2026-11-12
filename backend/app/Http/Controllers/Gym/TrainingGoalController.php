<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Models\GoalMilestone;
use App\Models\Gym;
use App\Models\TrainingGoal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * R-118: training goals and milestones. Coaches with training.athletes.manage edit any athlete's goals;
 * an athlete edits and ticks only their own. Viewing needs training.athletes.view (or being the athlete).
 */
class TrainingGoalController extends Controller
{
    public function index(Request $request, Gym $gym, User $user): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer->id === $user->id || $viewer->hasPermission($gym, 'training.athletes.view', 'training.athletes.manage'), 403);

        return response()->json(['goals' => $this->goals($user->id)]);
    }

    public function store(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->assertCanEdit($request, $gym, $user->id);
        abort_unless($user->hasGymRole($gym, GymPersonType::Member), 404);

        $data = $request->validate($this->goalRules() + [
            'milestones' => ['sometimes', 'array', 'max:20'],
            'milestones.*.title' => ['required', 'string', 'max:150'],
            'milestones.*.due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        DB::transaction(function () use ($request, $user, $data) {
            $goal = new TrainingGoal($data);
            $goal->user_id = $user->id;
            $goal->status = 'active';
            $goal->created_by = $request->user()->id;
            $goal->save();

            foreach ($data['milestones'] ?? [] as $position => $milestone) {
                $goal->milestones()->create([...$milestone, 'position' => $position]);
            }
        });

        return response()->json(['goals' => $this->goals($user->id)], 201);
    }

    public function update(Request $request, Gym $gym, int $goal): JsonResponse
    {
        $model = TrainingGoal::findOrFail($goal);
        $this->assertCanEdit($request, $gym, $model->user_id);

        $data = $request->validate($this->goalRules() + ['status' => ['sometimes', Rule::in(['active', 'achieved', 'dropped'])]]);
        $model->fill($data);

        if (array_key_exists('status', $data)) {
            $model->achieved_on = $data['status'] === 'achieved' ? ($model->achieved_on ?? today()) : null;
        }

        $model->save();

        return response()->json(['goals' => $this->goals($model->user_id)]);
    }

    public function destroy(Request $request, Gym $gym, int $goal): JsonResponse
    {
        $model = TrainingGoal::findOrFail($goal);
        $this->assertCanEdit($request, $gym, $model->user_id);
        $model->delete();

        return response()->json(['goals' => $this->goals($model->user_id)]);
    }

    public function storeMilestone(Request $request, Gym $gym, int $goal): JsonResponse
    {
        $model = TrainingGoal::findOrFail($goal);
        $this->assertCanEdit($request, $gym, $model->user_id);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'due_on' => ['nullable', 'date_format:Y-m-d']]);

        $model->milestones()->create([...$data, 'position' => (int) $model->milestones()->max('position') + 1]);
        $model->syncStatusFromMilestones();

        return response()->json(['goals' => $this->goals($model->user_id)], 201);
    }

    /** Rename, change the due date, or tick done / not done. */
    public function updateMilestone(Request $request, Gym $gym, int $milestone): JsonResponse
    {
        $model = GoalMilestone::with('goal')->findOrFail($milestone);
        $this->assertCanEdit($request, $gym, $model->goal->user_id);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'done' => ['sometimes', 'boolean'],
        ]);

        $model->fill(collect($data)->only(['title', 'due_on'])->all());

        if (array_key_exists('done', $data)) {
            $model->done_on = $data['done'] ? ($model->done_on ?? today()) : null;
            $model->done_by = $data['done'] ? $request->user()->id : null;
        }

        $model->save();
        $model->goal->syncStatusFromMilestones();

        return response()->json(['goals' => $this->goals($model->goal->user_id)]);
    }

    public function destroyMilestone(Request $request, Gym $gym, int $milestone): JsonResponse
    {
        $model = GoalMilestone::with('goal')->findOrFail($milestone);
        $this->assertCanEdit($request, $gym, $model->goal->user_id);
        $model->delete();
        $model->goal->syncStatusFromMilestones();

        return response()->json(['goals' => $this->goals($model->goal->user_id)]);
    }

    private function assertCanEdit(Request $request, Gym $gym, int $athleteId): void
    {
        $viewer = $request->user();
        abort_unless($viewer->id === $athleteId || $viewer->hasPermission($gym, 'training.athletes.manage'), 403, 'You can change only your own goals.');
    }

    private function goalRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private function goals(int $userId): array
    {
        return TrainingGoal::with(['milestones.doneBy', 'createdBy'])->where('user_id', $userId)
            ->orderByRaw("case when status = 'active' then 0 when status = 'achieved' then 1 else 2 end")->orderByDesc('id')->get()
            ->map(fn (TrainingGoal $goal) => [
                'id' => $goal->id,
                'title' => $goal->title,
                'description' => $goal->description,
                'target_on' => $goal->target_on?->toDateString(),
                'status' => $goal->status,
                'achieved_on' => $goal->achieved_on?->toDateString(),
                'created_by' => $goal->createdBy ? trim("{$goal->createdBy->first_name} {$goal->createdBy->last_name}") : null,
                'done_count' => $goal->milestones->whereNotNull('done_on')->count(),
                'milestones' => $goal->milestones->map(fn (GoalMilestone $milestone) => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'due_on' => $milestone->due_on?->toDateString(),
                    'done_on' => $milestone->done_on?->toDateString(),
                    'done_by' => $milestone->doneBy ? trim("{$milestone->doneBy->first_name} {$milestone->doneBy->last_name}") : null,
                ])->values(),
            ])->values()->all();
    }
}
