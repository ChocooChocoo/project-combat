<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-21: the gym's rooms. Retired rooms stay for history. */
class RoomController extends Controller
{
    public function index(Gym $gym): JsonResponse
    {
        return response()->json([
            'rooms' => $gym->rooms()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('rooms', 'name')->where(fn ($query) => $query->where('gym_id', $gym->id)),
            ],
        ]);

        $room = $gym->rooms()->create($data);

        return response()->json(['room' => $room->refresh()], 201);
    }

    public function update(Request $request, Gym $gym, int $room): JsonResponse
    {
        $model = $gym->rooms()->findOrFail($room);

        $data = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:80',
                Rule::unique('rooms', 'name')
                    ->where(fn ($query) => $query->where('gym_id', $gym->id))
                    ->ignore($model->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model->update($data);

        return response()->json(['room' => $model]);
    }

    /** Retiring keeps the room on record for sessions that already used it. */
    public function destroy(Gym $gym, int $room): JsonResponse
    {
        $model = $gym->rooms()->findOrFail($room);
        $model->update(['is_active' => false]);

        return response()->json(['room' => $model]);
    }
}
