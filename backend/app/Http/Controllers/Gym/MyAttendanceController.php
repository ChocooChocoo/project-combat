<?php

namespace App\Http\Controllers\Gym;

use App\Support\DateRange;
use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsAttendance;
use App\Models\Gym;
use App\Models\OvertimeRequest;
use App\Models\StaffAttendance;
use App\Support\StaffAttendanceBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-56 and R-57: an employee's own clock, records and overtime requests. */
class MyAttendanceController extends Controller
{
    use PresentsAttendance;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(DateRange::rules());
        $ranged = filled($data['from'] ?? null) || filled($data['to'] ?? null);

        $rows = DateRange::apply($gym->staffAttendance(), $data, 'work_date')
            ->with('user')
            ->where('user_id', $user->id)
            ->orderByDesc('work_date')
            ->limit($ranged ? 366 : 31)
            ->get();

        $overtime = DateRange::apply($gym->overtimeRequests(), $data, 'work_date')
            ->with(['user', 'decidedBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit($ranged ? 200 : 20)
            ->get();

        return response()->json([
            'attendance' => $rows->map(fn (StaffAttendance $row) => $this->attendancePayload($row)),
            'today' => ($today = $gym->staffAttendance()->with('user')->where('user_id', $user->id)->whereDate('work_date', today())->first()) ? $this->attendancePayload($today) : null,
            'overtime' => $overtime->map(fn (OvertimeRequest $row) => $this->overtimePayload($row)),
            'settings' => [
                'grace_minutes' => $gym->staff_grace_minutes,
                'overtime_min_minutes' => $gym->overtime_min_minutes,
            ],
        ]);
    }

    public function clockIn(Request $request, Gym $gym, StaffAttendanceBook $book): JsonResponse
    {
        $this->ensureEmployee($request, $gym);
        $row = $book->clockIn($gym, $request->user());

        return response()->json(['today' => $this->attendancePayload($row->load('user'))], 201);
    }

    public function clockOut(Request $request, Gym $gym, StaffAttendanceBook $book): JsonResponse
    {
        $this->ensureEmployee($request, $gym);
        $row = $book->clockOut($gym, $request->user());

        return response()->json(['today' => $this->attendancePayload($row->load('user'))]);
    }

    public function explain(Request $request, Gym $gym, int $entry, StaffAttendanceBook $book): JsonResponse
    {
        $this->ensureEmployee($request, $gym);

        $data = $request->validate([
            'explanation' => ['required', 'string', 'max:1000'],
        ]);

        $row = $gym->staffAttendance()->where('user_id', $request->user()->id)->findOrFail($entry);
        $row = $book->explain($request->user(), $row, $data['explanation']);

        return response()->json(['attendance' => $this->attendancePayload($row->load('user'))]);
    }

    private function ensureEmployee(Request $request, Gym $gym): void
    {
        abort_unless($request->user()->hasGymRole($gym, GymPersonType::Employee), 403, 'Only employees record their own attendance.');
    }
}
