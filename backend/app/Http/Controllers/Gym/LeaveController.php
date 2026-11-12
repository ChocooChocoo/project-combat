<?php

namespace App\Http\Controllers\Gym;

use App\Support\DateRange;
use App\Enums\GymPersonType;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\LeaveRequest;
use App\Models\TrainingSession;
use App\Notifications\LeaveRequestDecided;
use App\Notifications\LeaveRequestSubmitted;
use App\Support\PayrollLock;
use App\Support\SessionFlagger;
use App\Support\WorkingDays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** R-25 and R-26: leave requests, decisions, attachments and balances. */
class LeaveController extends Controller
{
    use PresentsHr;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(LeaveRequestStatus::class)],
            ...DateRange::rules(),
        ]);

        $viewer = $request->user();
        $canSeeAll = $viewer->hasPermission($gym, 'hr.leave.view', 'hr.leave.manage');

        $query = DateRange::overlap($gym->leaveRequests()->with(['user', 'decidedBy']), $data, 'starts_on', 'ends_on');

        if (! $canSeeAll) {
            $query->where('user_id', $viewer->id);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $requests = $query->orderByDesc('starts_on')->orderByDesc('id')->get()
            ->map(fn (LeaveRequest $leave) => $this->leavePayload($gym, $leave))
            ->values();

        return response()->json(['requests' => $requests]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 403, 'Only employees can file leave.');

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('hr.leave_types')))],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => [
                'nullable', 'file',
                'mimes:'.implode(',', config('hr.attachment.mimes')),
                'max:'.config('hr.attachment.max_kb'),
            ],
        ]);

        $from = Carbon::parse($data['starts_on'])->startOfDay();
        $to = Carbon::parse($data['ends_on'])->startOfDay();

        PayrollLock::assertOpenRange($gym, $from, $to);

        $path = $name = $mime = null;
        $size = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store("leave-attachments/{$gym->id}", 'local');
            $name = $file->getClientOriginalName();
            $mime = $file->getMimeType();
            $size = $file->getSize();
        }

        $leave = $gym->leaveRequests()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'starts_on' => $from->toDateString(),
            'ends_on' => $to->toDateString(),
            'working_days' => app(WorkingDays::class)->count($gym, $user, $from, $to),
            'reason' => $data['reason'],
            'status' => LeaveRequestStatus::Pending->value,
        ]);

        // The model keeps file metadata out of mass assignment; set it explicitly.
        $leave->forceFill([
            'attachment_path' => $path,
            'attachment_name' => $name,
            'attachment_mime' => $mime,
            'attachment_size' => $size,
        ])->save();

        $employeeName = trim("{$user->first_name} {$user->last_name}");
        foreach ($this->leaveApprovers($gym) as $approver) {
            $approver->notify(new LeaveRequestSubmitted($gym, $leave, $employeeName));
        }

        return response()->json(['request' => $this->leavePayload($gym, $leave)], 201);
    }

    public function show(Request $request, Gym $gym, int $leave): JsonResponse
    {
        $leave = $this->findLeave($gym, $leave);
        $viewer = $request->user();

        abort_unless(
            $viewer->id === $leave->user_id || $viewer->hasPermission($gym, 'hr.leave.view'),
            403,
        );

        return response()->json(['request' => $this->leavePayload($gym, $leave)]);
    }

    public function attachment(Request $request, Gym $gym, int $leave): StreamedResponse
    {
        $leave = $this->findLeave($gym, $leave);
        $viewer = $request->user();

        abort_unless($viewer->id === $leave->user_id || $viewer->canApproveLeave($gym), 403);
        abort_unless($leave->attachment_path, 404);

        return Storage::disk('local')->response(
            $leave->attachment_path,
            $leave->attachment_name ?? basename($leave->attachment_path),
        );
    }

    public function cancel(Request $request, Gym $gym, int $leave): JsonResponse
    {
        $leave = $this->findLeave($gym, $leave);

        abort_unless($request->user()->id === $leave->user_id, 403, 'You can only cancel your own request.');
        abort_unless($leave->status === LeaveRequestStatus::Pending, 409, 'Only pending requests can be cancelled.');

        $leave->update(['status' => LeaveRequestStatus::Cancelled->value]);

        return response()->json(['request' => $this->leavePayload($gym, $leave)]);
    }

    public function decide(Request $request, Gym $gym, int $leave): JsonResponse
    {
        $leave = $this->findLeave($gym, $leave);

        abort_if($request->user()->id === $leave->user_id, 403, 'You cannot decide your own request.');
        abort_unless($leave->status === LeaveRequestStatus::Pending, 409, 'Only pending requests can be decided.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $gym, $leave, $data) {
            // R-46: serialize with session saves, so approval cannot miss a concurrent occurrence.
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $leave = $gym->leaveRequests()->lockForUpdate()->findOrFail($leave->id);
            abort_unless($leave->status === LeaveRequestStatus::Pending, 409, 'Only pending requests can be decided.');
            PayrollLock::assertOpenRange($gym, $leave->starts_on, $leave->ends_on);
            $leave->status = $data['decision'] === 'approve'
                ? LeaveRequestStatus::Approved
                : LeaveRequestStatus::Rejected;
            $leave->decided_by = $request->user()->id;
            $leave->decided_at = now();
            $leave->decision_note = $data['note'] ?? null;
            $leave->save();

            $leave->user->notify(new LeaveRequestDecided($gym, $leave));
            if ($leave->status === LeaveRequestStatus::Approved) {
                $sessions = TrainingSession::where('coach_id', $leave->user_id)
                    ->where('starts_at', '>=', $leave->starts_on->copy()->startOfDay())
                    ->where('starts_at', '<', $leave->ends_on->copy()->addDay()->startOfDay());
                $name = trim("{$leave->user->first_name} {$leave->user->last_name}");
                app(SessionFlagger::class)->flag($gym, $sessions, 'Coach on approved leave ('.$leave->starts_on->format('M j, Y')
                    .($leave->starts_on->isSameDay($leave->ends_on) ? '' : ' to '.$leave->ends_on->format('M j, Y')).')', "{$name} is on approved leave");
            }
        });

        return response()->json(['request' => $this->leavePayload($gym, $leave->fresh(['user', 'decidedBy']))]);
    }

    public function balances(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2100'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);
        $viewer = $request->user();
        $canSeeAll = $viewer->hasPermission($gym, 'hr.leave.view', 'hr.leave.manage');

        if ($canSeeAll) {
            $employees = $gym->people()
                ->wherePivot('role', GymPersonType::Employee->value)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        } else {
            $employees = $viewer->hasGymRole($gym, GymPersonType::Employee)
                ? collect([$viewer])
                : collect();
        }

        $balances = $employees->map(fn ($employee) => [
            'user_id' => $employee->id,
            'user_name' => trim("{$employee->first_name} {$employee->last_name}"),
            'year' => $year,
            'types' => $this->leaveBalanceTypes($gym, $employee, $year),
        ])->values();

        return response()->json(['balances' => $balances]);
    }

    private function findLeave(Gym $gym, int $id): LeaveRequest
    {
        return $gym->leaveRequests()->with(['user', 'decidedBy'])->findOrFail($id);
    }
}
