<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\GymStatus;
use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\GymMemberReview;
use App\Models\Membership;
use App\Models\User;
use App\Support\Paging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GymMemberReviewController extends Controller
{
    public function index(Request $request, Gym $gym): JsonResponse
    {
        $member = $this->member($request, $gym);
        $request->validate(Paging::rules());

        $reviews = $this->forGym($gym);
        $summary = (clone $reviews)->toBase()
            ->selectRaw('count(*) as rating_count, avg(rating) as average_rating')
            ->first();
        $mine = (clone $reviews)->where('user_id', $member->id)->first();
        $page = $reviews->orderByDesc('id')->simplePaginate(10);

        return response()->json([
            'rating_count' => (int) $summary->rating_count,
            'average_rating' => $summary->average_rating === null ? null : round((float) $summary->average_rating, 1),
            'reviews' => $page->getCollection()->map($this->present(...)),
            'has_more' => $page->hasMorePages(),
            'can_review' => $this->canReview($gym, $member),
            'my_review' => $mine ? $this->present($mine) : null,
        ]);
    }

    public function save(Request $request, Gym $gym): Response
    {
        $member = $this->member($request, $gym);
        abort_unless($this->canReview($gym, $member), 403, 'You need an active membership at this gym to leave or edit a review.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        GymMemberReview::upsert([
            'gym_id' => $gym->id,
            'user_id' => $member->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ], ['gym_id', 'user_id'], ['rating', 'comment']);

        return response()->noContent();
    }

    public function destroy(Request $request, Gym $gym): Response
    {
        $member = $this->member($request, $gym);
        $this->forGym($gym)->where('user_id', $member->id)->delete();

        return response()->noContent();
    }

    private function member(Request $request, Gym $gym): User
    {
        $user = $request->user();
        abort_unless($user->account_type === AccountType::Member && ! $user->is_platform_admin, 403);
        abort_unless($gym->status === GymStatus::Approved, 404);

        return $user;
    }

    private function canReview(Gym $gym, User $member): bool
    {
        return Membership::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->where('user_id', $member->id)
            ->where('status', MembershipStatus::Active)
            ->exists();
    }

    /** Readers and former members don't need a gym workspace role. */
    private function forGym(Gym $gym): Builder
    {
        return GymMemberReview::withoutGlobalScope('gym')->where('gym_id', $gym->id);
    }

    private function present(GymMemberReview $review): array
    {
        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at?->toISOString(),
        ];
    }
}
