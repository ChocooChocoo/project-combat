<?php

namespace App\Http\Controllers\Gym;

use App\Support\DateRange;
use App\Enums\GymPersonType;
use App\Enums\OvertimeStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsAttendance;
use App\Models\Gym;
use App\Models\OvertimeRequest;
use App\Notifications\OvertimeDecided;
use App\Notifications\OvertimeRequested;
use App\Support\PayrollLock;
use App\Support\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** R-57: extra time becomes a request, and a different permitted person decides it. */
class OvertimeController extends Controller
{
    use PresentsAttendance;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(OvertimeStatus::class)],
            ...DateRange::rules(),
            ...Paging::rules(),
        ]);

        $query = DateRange::apply($gym->overtimeRequests(), $data, 'work_date')
            ->with(['user', 'decidedBy'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('work_date')
            ->orderByDesc('id');
        [$rows, $meta] = Paging::rows($query, $request, 200);

        return response()->json(['requests' => $rows->map(fn (OvertimeRequest $row) => $this->overtimePayload($row)), ...($meta ? ['meta' => $meta] : [])]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 403, 'Only employees file overtime.');

        $data = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'],
            'minutes' => ['required', 'integer', 'between:1,1440'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($data['work_date'])->startOfDay();
        PayrollLock::assertOpen($gym, $date);

        $row = $gym->overtimeRequests()->create([
            'user_id' => $user->id,
            'work_date' => $date->toDateString(),
            'kind' => 'manual',
            'requested_minutes' => $data['minutes'],
            'reason' => $data['reason'],
            'status' => OvertimeStatus::Pending->value,
        ]);

        $name = trim("{$user->first_name} {$user->last_name}");
        foreach ($gym->peopleWithPermission('hr.overtime.approve') as $approver) {
            if ($approver->id !== $user->id) {
                $approver->notify(new OvertimeRequested($gym, $row, $name));
            }
        }

        return response()->json(['request' => $this->overtimePayload($row->load(['user', 'decidedBy']))], 201);
    }

    public function decide(Request $request, Gym $gym, int $overtime): JsonResponse
    {
        $row = $gym->overtimeRequests()->findOrFail($overtime);

        abort_if($row->user_id === $request->user()->id, 403, 'You cannot decide your own overtime request.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'minutes' => ['nullable', 'integer', 'between:0,1440'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = DB::transaction(function () use ($request, $gym, $row, $data) {
            $row = $gym->overtimeRequests()->lockForUpdate()->findOrFail($row->id);
            abort_unless($row->status === OvertimeStatus::Pending, 409, 'Only a pending request can be decided.');
            PayrollLock::assertOpen($gym, $row->work_date);

            $approved = $data['decision'] === 'approve'
                ? min($row->requested_minutes, (int) ($data['minutes'] ?? $row->requested_minutes))
                : null;

            $row->update([
                'status' => ($data['decision'] === 'approve' ? OvertimeStatus::Approved : OvertimeStatus::Rejected)->value,
                'approved_minutes' => $approved,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => $data['note'] ?? null,
            ]);

            $row->user->notify(new OvertimeDecided($gym, $row));

            return $row;
        });

        return response()->json(['request' => $this->overtimePayload($row->fresh(['user', 'decidedBy']))]);
    }
}
