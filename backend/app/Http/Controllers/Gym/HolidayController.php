<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsHr;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        if ($gym->holidays()->whereDate('date', $data['date'])->exists()) {
            throw ValidationException::withMessages([
                'date' => 'This date already has a holiday.',
            ]);
        }

        $holiday = $gym->holidays()->create($data);

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
        $gym->holidays()->findOrFail($holiday)->delete();

        return response()->json(['message' => 'Holiday removed.']);
    }
}
