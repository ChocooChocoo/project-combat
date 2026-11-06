<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsAttendance;
use App\Models\Gym;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Support\StaffAttendanceBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-56: the desk sees every employee's attendance and corrects mistakes with a reason. */
class StaffAttendanceController extends Controller
{
    use PresentsAttendance;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $gym->staffAttendance()
            ->with(['user', 'recordedBy'])
            ->withCount('changes')
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('work_date', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('work_date', '<=', $to))
            ->when($data['user_id'] ?? null, fn ($query, $user) => $query->where('user_id', $user))
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'attendance' => collect($page->items())->map(fn (StaffAttendance $row) => $this->attendancePayload($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** The employee choices for a desk entry. Separate from the staff list, which needs another permission. */
    public function employees(Gym $gym): JsonResponse
    {
        $employees = $gym->employees()->with('user')->get()
            ->filter(fn ($profile) => $profile->user)
            ->map(fn ($profile) => [
                'id' => $profile->user->id,
                'name' => trim("{$profile->user->first_name} {$profile->user->last_name}"),
            ])
            ->sortBy('name')
            ->values();

        return response()->json(['employees' => $employees]);
    }

    public function store(Request $request, Gym $gym, StaffAttendanceBook $book): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'clock_in_time' => ['nullable', 'date_format:H:i'],
            'clock_out_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = $this->employee($gym, (int) $data['user_id']);
        $row = $book->deskEntry($gym, $employee, $data, $request->user());

        return response()->json(['attendance' => $this->attendancePayload($row->load(['user', 'recordedBy']))], 201);
    }

    public function update(Request $request, Gym $gym, int $entry, StaffAttendanceBook $book): JsonResponse
    {
        $row = $gym->staffAttendance()->findOrFail($entry);

        $data = $request->validate([
            'clock_in_time' => ['nullable', 'date_format:H:i'],
            'clock_out_time' => ['nullable', 'date_format:H:i'],
            'scheduled_start' => ['nullable', 'date_format:H:i'],
            'scheduled_end' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $row = $book->correct($gym, $row, $data, $request->user(), $data['reason']);

        return response()->json(['attendance' => $this->attendancePayload($row->load(['user', 'recordedBy']))]);
    }

    public function changes(Gym $gym, int $entry): JsonResponse
    {
        $row = $gym->staffAttendance()->findOrFail($entry);

        return response()->json(['changes' => $row->changes()->with('changedBy')->get()->map(fn ($change) => [
            'id' => $change->id,
            'changed_by_name' => $change->changedBy ? trim("{$change->changedBy->first_name} {$change->changedBy->last_name}") : null,
            'reason' => $change->reason,
            'before' => $change->before,
            'after' => $change->after,
            'created_at' => $change->created_at?->format('Y-m-d H:i'),
        ])]);
    }

    private function employee(Gym $gym, int $userId): User
    {
        $user = User::findOrFail($userId);
        abort_unless($gym->employees()->where('user_id', $user->id)->exists(), 422, 'Choose an employee of this gym.');

        return $user;
    }
}
