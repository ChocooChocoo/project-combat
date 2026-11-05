<?php

namespace App\Http\Controllers\Gym;

use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\AttendanceLedger;
use App\Support\CheckInCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** R-52 to R-54: credentials are personal; every attendance write is staff-only. */
class AttendanceController extends Controller
{
    public function code(Request $request, Gym $gym, CheckInCode $codes): JsonResponse
    {
        abort_unless(Membership::where('user_id', $request->user()->id)->where('status', MembershipStatus::Active)->exists(), 403, 'An active membership is required.');

        return response()->json($codes->issue($gym, $request->user()));
    }

    public function members(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $users = User::whereIn('id', Membership::where('status', MembershipStatus::Active)->select('user_id'))
            ->when($data['search'] ?? null, function ($q, $search) {
                foreach (preg_split('/\s+/', trim($search)) as $word) {
                    $q->where(fn ($q) => $q->where('first_name', 'like', "%{$word}%")->orWhere('last_name', 'like', "%{$word}%"));
                }
            })->orderBy('first_name')->orderBy('last_name')->limit(30)->get();

        return response()->json(['members' => $users->map(fn ($user) => ['id' => $user->id, 'name' => trim("{$user->first_name} {$user->last_name}")])]);
    }

    public function today(Request $request, Gym $gym, AttendanceLedger $ledger): JsonResponse
    {
        return response()->json(['check_ins' => $ledger->rows(['checked_in_on' => today()->toDateString()])->sortByDesc('check_in_at')->values()]);
    }

    public function store(Request $request, Gym $gym, CheckInCode $codes, AttendanceLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required_without:user_id', 'prohibits:user_id', 'string', 'max:200'],
            'user_id' => ['required_without:code', 'prohibits:code', 'integer'],
        ]);
        $user = isset($data['code']) ? $codes->resolve($gym, $data['code']) : User::whereIn('id', Membership::where('status', MembershipStatus::Active)->select('user_id'))->find($data['user_id']);
        abort_unless($user, 422, 'Choose a member with an active membership at this gym.');

        return response()->json(['check_in' => $ledger->checkIn($user, $request->user(), isset($data['code']) ? 'qr' : 'account')], 201);
    }

    public function attendance(Request $request, Gym $gym, int $booking, AttendanceLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'attendance' => ['required', Rule::in(['present', 'late', 'absent'])],
            'check_in_at' => ['nullable', 'date_format:H:i,Y-m-d H:i:s'],
        ]);
        $row = DB::transaction(function () use ($request, $booking, $ledger, $data) {
            $model = SessionBooking::lockForUpdate()->findOrFail($booking);
            abort_unless($model->status === BookingStatus::Booked, 422, 'Only booked athletes can be marked.');
            abort_if($model->session->starts_at->isFuture() && ! $model->session->starts_at->isToday(), 422, 'Attendance opens on the session date.');
            $at = ! empty($data['check_in_at']) ? Carbon::parse(strlen($data['check_in_at']) === 5 ? $model->session->starts_at->toDateString().' '.$data['check_in_at'] : $data['check_in_at']) : now();
            abort_if($data['attendance'] !== 'absent' && $model->check_out_at && $at->gt($model->check_out_at), 422, 'Check-in cannot be after check-out.');
            $ledger->mark($model, $data['attendance'], $request->user(), 'manual', $at);

            return $ledger->bookingRow($model->load(['user', 'attendanceBy']));
        });

        return response()->json(['attendance' => $row]);
    }

    public function checkOut(Request $request, Gym $gym, int $booking, AttendanceLedger $ledger): JsonResponse
    {
        $row = DB::transaction(function () use ($booking, $ledger) {
            $model = SessionBooking::lockForUpdate()->findOrFail($booking);
            abort_unless($model->check_in_at && in_array($model->attendance, ['present', 'late'], true), 422, 'Check in before checking out.');
            abort_if($model->check_out_at, 422, 'Already checked out.');
            abort_if($model->check_in_at->gt(now()), 422, 'Check-out cannot be before check-in.');
            $model->forceFill(['check_out_at' => now()])->save();

            return $ledger->bookingRow($model->load(['user', 'session', 'attendanceBy']));
        });

        return response()->json(['attendance' => $row]);
    }

    public function index(Request $request, Gym $gym, AttendanceLedger $ledger): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'session_id' => ['sometimes', 'integer'], 'gym_service_id' => ['sometimes', 'integer'], 'user_id' => ['sometimes', 'integer'],
        ]);

        return response()->json([
            'attendance' => $ledger->rows($filters),
            'sessions' => TrainingSession::orderByDesc('starts_at')->get(['id', 'name', 'starts_at'])
                ->map(fn ($session) => ['id' => $session->id, 'name' => $session->name, 'starts_at' => $session->starts_at->format('Y-m-d H:i:s')]),
            'classes' => $gym->gymServices()->orderBy('name')->get(['id', 'name']),
            'members' => $gym->people()->wherePivot('role', 'member')->orderBy('first_name')->get()->map(fn ($user) => ['id' => $user->id, 'name' => trim("{$user->first_name} {$user->last_name}")]),
        ]);
    }
}
