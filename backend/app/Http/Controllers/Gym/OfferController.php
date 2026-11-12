<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\GymNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * R-111: promos and vouchers on membership plans. Staff-made offers wait for an approver;
 * offers the owner makes or edits are approved at once. Nobody decides their own offer.
 */
class OfferController extends Controller
{
    public function index(Gym $gym): JsonResponse
    {
        $offers = Offer::with(['plans', 'createdBy', 'decidedBy'])
            ->orderByRaw("case when status = 'pending' then 0 when status = 'approved' then 1 else 2 end")
            ->orderByDesc('id')->get();

        return response()->json([
            'offers' => $offers->map(fn (Offer $offer) => $this->payload($offer))->values(),
            'plans' => $gym->membershipPlans()->orderBy('name')->get(['id', 'name', 'total_fee']),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $this->validated($request, $gym);
        $user = $request->user();

        $offer = DB::transaction(function () use ($gym, $data, $user) {
            $offer = new Offer($data);
            $offer->created_by = $user->id;
            $this->setStatusAfterEdit($gym, $offer, $user);
            $offer->save();
            $offer->plans()->sync($data['plan_ids']);

            return $offer;
        });

        $this->notifyIfPending($gym, $offer, $user);

        return response()->json(['offer' => $this->payload($offer->fresh(['plans', 'createdBy', 'decidedBy']))], 201);
    }

    public function update(Request $request, Gym $gym, int $offer): JsonResponse
    {
        $model = Offer::findOrFail($offer);
        abort_if($model->status === 'archived', 409, 'Archived offers cannot be edited.');
        $data = $this->validated($request, $gym, $model);
        $user = $request->user();

        DB::transaction(function () use ($gym, $model, $data, $user) {
            $model->fill($data);
            $this->setStatusAfterEdit($gym, $model, $user);
            $model->save();
            $model->plans()->sync($data['plan_ids']);
        });

        $this->notifyIfPending($gym, $model, $user);

        return response()->json(['offer' => $this->payload($model->fresh(['plans', 'createdBy', 'decidedBy']))]);
    }

    public function decide(Request $request, Gym $gym, int $offer): JsonResponse
    {
        $model = Offer::with('createdBy')->findOrFail($offer);
        $user = $request->user();

        abort_if((int) $model->created_by === (int) $user->id, 403, 'You cannot decide your own offer.');
        abort_unless($model->status === 'pending', 409, 'Only waiting offers can be decided.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ], ['reason.required_if' => 'Give a reason.']);

        $model->status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
        $model->decided_by = $user->id;
        $model->decided_at = now();
        $model->decision_reason = $data['reason'] ?? null;
        $model->save();

        $model->createdBy?->notify(new GymNotice(
            $model->status === 'approved' ? 'Offer approved' : 'Offer rejected',
            "{$model->name} was {$model->status}.".($model->decision_reason ? " Reason: {$model->decision_reason}" : ''),
            $gym->id,
            "/gyms/{$gym->id}/manage/offers",
        ));

        return response()->json(['offer' => $this->payload($model->fresh(['plans', 'createdBy', 'decidedBy']))]);
    }

    public function archive(Gym $gym, int $offer): JsonResponse
    {
        $model = Offer::findOrFail($offer);
        $model->status = 'archived';
        $model->save();

        return response()->json(['offer' => $this->payload($model->fresh(['plans', 'createdBy', 'decidedBy']))]);
    }

    private function setStatusAfterEdit(Gym $gym, Offer $offer, User $user): void
    {
        if ($user->hasGymRole($gym, GymPersonType::Owner)) {
            $offer->status = 'approved';
            $offer->decided_by = $user->id;
            $offer->decided_at = now();
            $offer->decision_reason = null;

            return;
        }

        $offer->status = 'pending';
        $offer->decided_by = null;
        $offer->decided_at = null;
        $offer->decision_reason = null;
    }

    private function notifyIfPending(Gym $gym, Offer $offer, User $actor): void
    {
        if ($offer->status !== 'pending') {
            return;
        }

        $gym->peopleWithPermission('customers.offers.approve')
            ->reject(fn (User $person) => $person->id === $actor->id)
            ->each(fn (User $person) => $person->notify(new GymNotice(
                'Offer waiting for approval',
                trim("{$actor->first_name} {$actor->last_name}")." submitted {$offer->name}.",
                $gym->id,
                "/gyms/{$gym->id}/manage/offers",
            )));
    }

    private function validated(Request $request, Gym $gym, ?Offer $offer = null): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['promo', 'voucher'])],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable', 'required_if:kind,voucher', 'string', 'max:40', 'alpha_dash',
                Rule::unique('offers', 'code')->where('gym_id', $gym->id)->ignore($offer?->id),
            ],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', Rule::when($request->input('discount_type') === 'percent', 'max:100')],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'plan_ids' => ['required', 'array', 'min:1'],
            'plan_ids.*' => ['integer', 'distinct', Rule::exists('membership_plans', 'id')->where('gym_id', $gym->id)],
        ], [
            'code.required_if' => 'A voucher needs a code.',
            'plan_ids.required' => 'Choose at least one plan.',
            'value.max' => 'A percent discount cannot be more than 100.',
        ]);

        $data['code'] = $data['kind'] === 'voucher' ? strtoupper($data['code']) : null;

        return $data;
    }

    private function payload(Offer $offer): array
    {
        $name = fn (?User $user) => $user ? trim("{$user->first_name} {$user->last_name}") : null;

        return [
            'id' => $offer->id,
            'kind' => $offer->kind,
            'name' => $offer->name,
            'code' => $offer->code,
            'discount_type' => $offer->discount_type,
            'value' => $offer->value,
            'starts_on' => $offer->starts_on?->toDateString(),
            'ends_on' => $offer->ends_on?->toDateString(),
            'max_uses' => $offer->max_uses,
            'uses' => $offer->uses,
            'status' => $offer->status,
            'plans' => $offer->plans->map(fn ($plan) => ['id' => $plan->id, 'name' => $plan->name])->values(),
            'created_by' => $name($offer->createdBy),
            'created_by_id' => $offer->created_by,
            'decided_by' => $name($offer->decidedBy),
            'decided_at' => $offer->decided_at?->toIso8601String(),
            'decision_reason' => $offer->decision_reason,
        ];
    }
}
