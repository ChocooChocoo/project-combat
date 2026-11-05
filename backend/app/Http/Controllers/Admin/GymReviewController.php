<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Notifications\GymApplicationDecided;
use App\Support\SystemRoles;
use App\Support\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-16 and R-17: the administrator checks each application and records a decision. */
class GymReviewController extends Controller
{
    private const OPEN = [GymStatus::Submitted, GymStatus::UnderReview];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(['submitted', 'under_review', 'returned', 'approved', 'rejected'])]]);

        return response()->json([
            'applications' => Gym::with('applicant:id,first_name,last_name,email')
                ->when(
                    $request->status,
                    fn ($q, $status) => $q->where('status', $status),
                    fn ($q) => $q->whereIn('status', self::OPEN),
                )
                ->orderBy('submitted_at')
                ->get(['id', 'applicant_id', 'name', 'city', 'status', 'tier', 'submitted_at', 'reviewed_at']),
        ]);
    }

    /** Opening a newly sent application marks it under review. */
    public function show(Gym $gym): JsonResponse
    {
        abort_if($gym->status === GymStatus::Draft, 404);

        if ($gym->status === GymStatus::Submitted) {
            $gym->forceFill(['status' => GymStatus::UnderReview])->save();
        }

        return response()->json([
            'application' => $gym->load('documents', 'applicant:id,first_name,last_name,email,phone'),
            'checklist_items' => Verification::checklistFor($gym->tier),
        ]);
    }

    public function checklist(Request $request, Gym $gym): JsonResponse
    {
        $this->ensureOpen($gym);
        $items = Verification::checklistFor($gym->tier);

        $data = $request->validate([
            'checklist' => ['required', 'array:'.implode(',', $items)],
            'checklist.*' => ['nullable', 'boolean'],
        ]);

        $gym->forceFill(['checklist' => array_merge($gym->checklist ?? [], $data['checklist'])])->save();

        return response()->json(['checklist' => $gym->checklist]);
    }

    public function decide(Request $request, Gym $gym): JsonResponse
    {
        $this->ensureOpen($gym);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'return'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
            'items' => ['required_if:decision,return', 'array', 'max:30'],
            'items.*' => ['string', 'max:255'],
        ], [
            'reason.required_if' => 'Give the reason for rejecting.',
            'items.required_if' => 'Name at least one thing to fix.',
        ]);

        if ($data['decision'] === 'approve') {
            $failed = array_filter(Verification::checklistFor($gym->tier), fn ($item) => ($gym->checklist[$item] ?? null) !== true);

            if ($failed) {
                throw ValidationException::withMessages(['checklist' => 'Every checklist item must pass before approving.']);
            }
        }

        DB::transaction(function () use ($gym, $data, $request) {
            $gym->forceFill([
                'status' => match ($data['decision']) {
                    'approve' => GymStatus::Approved,
                    'reject' => GymStatus::Rejected,
                    'return' => GymStatus::Returned,
                },
                'decision_reason' => $data['reason'] ?? null,
                'return_items' => $data['decision'] === 'return' ? $data['items'] : null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // R-18: the gym goes live with its tier badge, and the applicant becomes its owner.
            if ($data['decision'] === 'approve') {
                $gym->approved_tier = $gym->tier;
                $gym->addPerson($gym->applicant, GymPersonType::Owner);
                SystemRoles::seed($gym);
            }

            $gym->save();
        });

        $gym->applicant->notify(new GymApplicationDecided($gym));

        return response()->json(['application' => $gym->load('documents')]);
    }

    private function ensureOpen(Gym $gym): void
    {
        abort_if($gym->status === GymStatus::Draft, 404);
        abort_unless(in_array($gym->status, self::OPEN, true), 409, 'This application has already been decided or is back with the applicant.');
    }
}
