<?php

namespace App\Http\Controllers\Gym;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\SessionNote;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionNoteController extends Controller
{
    /** R-55: the assigned coach may write even without training manage. */
    public function store(Request $request, Gym $gym, int $session): JsonResponse
    {
        $model = TrainingSession::findOrFail($session);
        abort_unless($model->coach_id === $request->user()->id || $request->user()->hasPermission($gym, 'training.sessions.manage'), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'user_id' => ['nullable', 'integer']]);
        if (isset($data['user_id']) && ! $model->bookings()->where('user_id', $data['user_id'])->where('status', BookingStatus::Booked)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Choose a booked participant in this session.']);
        }
        $note = SessionNote::create([...$data, 'training_session_id' => $model->id, 'author_id' => $request->user()->id]);

        return response()->json(['note' => $note->load(['session', 'author'])->payload()], 201);
    }
}
