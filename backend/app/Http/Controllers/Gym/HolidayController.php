<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use App\Models\TrainingSession;
use App\Support\PayrollLock;
use App\Support\SessionFlagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** R-27: national holidays and the gym's own extra holidays. */
class HolidayController extends Controller
{
    use PresentsHr;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2100'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);

        return response()->json(['holidays' => $this->holidaysFor($gym, $year)]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        PayrollLock::assertOpen($gym, Carbon::parse($data['date'])->startOfDay());

        $holiday = DB::transaction(function () use ($gym, $data) {
            // R-46: same lock order as session saves, so no occurrence is missed.
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            if ($gym->holidays()->whereDate('date', $data['date'])->exists()) {
                throw ValidationException::withMessages([
                    'date' => 'This date already has a holiday.',
                ]);
            }
            $holiday = $gym->holidays()->create($data);

            // D-32: sessions on the new holiday need attention.
            $day = Carbon::parse($data['date'])->startOfDay();
            app(SessionFlagger::class)->flag($gym,
                TrainingSession::where('starts_at', '>=', $day)->where('starts_at', '<', $day->copy()->addDay()),
                "Holiday: {$data['name']}", "{$day->format('M j, Y')} is now a holiday ({$data['name']})");

            return $holiday;
        });

        return response()->json([
            'holiday' => [
                'id' => $holiday->id,
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
                'national' => false,
            ],
        ], 201);
    }

    public function destroy(Gym $gym, int $holiday): JsonResponse
    {
        $row = $gym->holidays()->findOrFail($holiday);
        PayrollLock::assertOpen($gym, $row->date);
        $row->delete();

        return response()->json(['message' => 'Holiday removed.']);
    }
}
