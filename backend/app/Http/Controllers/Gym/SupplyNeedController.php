<?php

namespace App\Http\Controllers\Gym;

use App\Enums\SupplyNeedStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsProcurement;
use App\Models\Gym;
use App\Models\SupplyNeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** R-75: the hand-entered needs that feed the restock list. */
class SupplyNeedController extends Controller
{
    use PresentsProcurement;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(SupplyNeedStatus::cases(), 'value'), 'all'])],
            'stock_item_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = ($data['status'] ?? null) === 'all' ? null : ($data['status'] ?? null);

        $needs = $gym->supplyNeeds()
            ->with(['stockItem', 'trainingSession', 'createdBy'])
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->when($data['stock_item_id'] ?? null, fn ($query, $item) => $query->where('stock_item_id', $item))
            ->orderByRaw("case when status = 'open' then 0 when status = 'requested' then 1 else 2 end")
            ->orderBy('needed_by')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'needs' => $needs->map(fn (SupplyNeed $need) => $this->supplyNeedPayload($need))->values(),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')
                ->where('gym_id', $gym->id)->where('status', 'active')],
            'training_session_id' => ['nullable', 'integer', Rule::exists('training_sessions', 'id')->where('gym_id', $gym->id)],
            'needed_by' => ['nullable', 'date_format:Y-m-d'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $need = $gym->supplyNeeds()->create([
            ...$data,
            'status' => SupplyNeedStatus::Open->value,
            'created_by' => $request->user()->id,
        ])->load(['stockItem', 'trainingSession', 'createdBy']);

        return response()->json(['need' => $this->supplyNeedPayload($need)], 201);
    }

    public function destroy(Gym $gym, int $need): JsonResponse|Response
    {
        $gym->supplyNeeds()->findOrFail($need)->delete();

        return response()->noContent();
    }
}
