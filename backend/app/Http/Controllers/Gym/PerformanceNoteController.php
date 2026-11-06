<?php

namespace App\Http\Controllers\Gym;

use App\Enums\PerformanceNoteKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsAttendance;
use App\Models\Gym;
use App\Models\PerformanceNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-58: dated notes about an employee, kept away from the employee they describe. */
class PerformanceNoteController extends Controller
{
    use PresentsAttendance;

    public function index(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->ensureTarget($request, $gym, $user);

        $notes = $gym->performanceNotes()
            ->with('author')
            ->where('user_id', $user->id)
            ->orderByDesc('noted_on')
            ->orderByDesc('id')
            ->get();

        return response()->json(['notes' => $notes->map(fn (PerformanceNote $note) => $this->performancePayload($note))]);
    }

    public function store(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->ensureTarget($request, $gym, $user);

        $data = $request->validate([
            'noted_on' => ['required', 'date_format:Y-m-d'],
            'kind' => ['required', Rule::enum(PerformanceNoteKind::class)],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = $gym->performanceNotes()->create([
            'user_id' => $user->id,
            'author_id' => $request->user()->id,
            'noted_on' => $data['noted_on'],
            'kind' => $data['kind'],
            'body' => $data['body'],
        ]);

        return response()->json(['note' => $this->performancePayload($note->load('author'))], 201);
    }

    private function ensureTarget(Request $request, Gym $gym, User $user): void
    {
        abort_unless($gym->employees()->where('user_id', $user->id)->exists(), 404);
        abort_if($request->user()->id === $user->id, 403, 'You cannot read or write notes about yourself.');
    }
}
