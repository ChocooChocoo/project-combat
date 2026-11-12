<?php

namespace App\Http\Controllers\Gym;

use App\Support\DateRange;
use App\Enums\PurchaseRequestStatus;
use App\Enums\SupplyNeedStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsProcurement;
use App\Models\Gym;
use App\Models\PurchaseRequest;
use App\Models\SupplyNeed;
use App\Support\Procurement\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-76: requests with lines, a different approver, rejection reasons and cancellation. */
class PurchaseRequestController extends Controller
{
    use PresentsProcurement;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(PurchaseRequestStatus::cases(), 'value'), 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
            ...DateRange::rules(),
        ]);
        $status = ($data['status'] ?? null) === 'all' ? null : ($data['status'] ?? null);

        $page = DateRange::apply($gym->purchaseRequests(), $data, 'created_at')
            ->with(['lines.stockItem', 'requestedBy', 'decidedBy', 'order'])
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'requests' => collect($page->items())->map(fn (PurchaseRequest $row) => $this->requestPayload($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request, Gym $gym, Notifier $notifier): JsonResponse
    {
        $data = $request->validate($this->rules($gym));

        $purchaseRequest = DB::transaction(function () use ($request, $gym, $data) {
            $row = $gym->purchaseRequests()->create([
                'number' => 'TMP-'.Str::upper(Str::random(16)),
                'status' => PurchaseRequestStatus::Pending->value,
                'requested_by' => $request->user()->id,
                'needed_by' => $data['needed_by'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $row->forceFill(['number' => sprintf('PR-%d-%06d', $gym->id, $row->id)])->save();
            $this->writeLines($row, $data['lines']);

            return $row->fresh(['lines.stockItem', 'requestedBy', 'decidedBy', 'order']);
        });

        $notifier->requestSubmitted($gym, $purchaseRequest, $request->user());

        return response()->json(['request' => $this->requestPayload($purchaseRequest)], 201);
    }

    /** R-113: the requester (or an approver) can change a request while it is still pending. */
    public function update(Request $request, Gym $gym, int $purchaseRequest): JsonResponse
    {
        $row = $gym->purchaseRequests()->findOrFail($purchaseRequest);
        $user = $request->user();

        abort_unless(
            (int) $row->requested_by === (int) $user->id || $user->hasPermission($gym, 'procurement.requests.approve'),
            403,
            'Only the requester or an approver can edit this request.',
        );

        $data = $request->validate($this->rules($gym));

        $row = DB::transaction(function () use ($row, $data) {
            $locked = $row->newQuery()->lockForUpdate()->findOrFail($row->id);
            abort_unless($locked->isPending(), 409, 'Only a pending request can be edited.');

            $locked->forceFill(['needed_by' => $data['needed_by'] ?? null, 'note' => $data['note'] ?? null])->save();
            $this->reopenNeeds($locked);
            $locked->lines()->delete();
            $this->writeLines($locked, $data['lines']);

            return $locked->fresh(['lines.stockItem', 'requestedBy', 'decidedBy', 'order']);
        });

        return response()->json(['request' => $this->requestPayload($row)]);
    }

    private function rules(Gym $gym): array
    {
        return [
            'needed_by' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')
                ->where('gym_id', $gym->id)->where('status', 'active')],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.est_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'lines.*.supply_need_id' => ['nullable', 'integer', Rule::exists('supply_needs', 'id')->where('gym_id', $gym->id)],
        ];
    }

    private function writeLines(PurchaseRequest $row, array $lines): void
    {
        $needIds = [];

        foreach ($lines as $line) {
            $row->lines()->create([
                'stock_item_id' => $line['stock_item_id'],
                'quantity' => $line['quantity'],
                'est_unit_cost' => isset($line['est_unit_cost'])
                    ? number_format((float) $line['est_unit_cost'], 2, '.', '')
                    : null,
                'supply_need_id' => $line['supply_need_id'] ?? null,
            ]);

            if (! empty($line['supply_need_id'])) {
                $needIds[] = $line['supply_need_id'];
            }
        }

        if ($needIds !== []) {
            SupplyNeed::query()->whereIn('id', array_values(array_unique($needIds)))
                ->update(['status' => SupplyNeedStatus::Requested->value]);
        }
    }

    public function decide(Request $request, Gym $gym, int $purchaseRequest, Notifier $notifier): JsonResponse
    {
        $row = $gym->purchaseRequests()->with(['lines.stockItem', 'requestedBy', 'decidedBy'])->findOrFail($purchaseRequest);
        $data = $request->validate([
            'decision' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'decision_reason' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $decision = $this->decisionValue($data['decision'] ?? $data['status'] ?? null);
        $reason = trim((string) ($data['decision_reason'] ?? $data['reason'] ?? ''));

        if ($decision === null) {
            throw ValidationException::withMessages(['decision' => 'Choose approved or rejected.']);
        }

        abort_if(
            (int) $row->requested_by === (int) $request->user()->id,
            403,
            'A purchase request must be decided by someone other than the requester.',
        );
        abort_unless($row->isPending(), 409, 'Only a pending request can be decided.');

        if ($decision === PurchaseRequestStatus::Rejected->value && $reason === '') {
            throw ValidationException::withMessages([
                'decision_reason' => 'Say why this request is rejected.',
                'reason' => 'Say why this request is rejected.',
            ]);
        }

        $row = DB::transaction(function () use ($row, $request, $decision, $reason) {
            $locked = $row->newQuery()->lockForUpdate()->findOrFail($row->id);
            abort_unless($locked->isPending(), 409, 'Only a pending request can be decided.');

            $locked->forceFill([
                'status' => $decision,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_reason' => $reason === '' ? null : $reason,
            ])->save();

            if ($decision === PurchaseRequestStatus::Rejected->value) {
                $this->reopenNeeds($locked);
            }

            return $locked->fresh(['lines.stockItem', 'requestedBy', 'decidedBy', 'order']);
        });

        $notifier->requestDecided($gym, $row);

        return response()->json(['request' => $this->requestPayload($row)]);
    }

    public function cancel(Request $request, Gym $gym, int $purchaseRequest, Notifier $notifier): JsonResponse
    {
        $row = $gym->purchaseRequests()->with(['lines.stockItem', 'requestedBy', 'decidedBy'])->findOrFail($purchaseRequest);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'decision_reason' => ['nullable', 'string', 'max:5000'],
            'cancel_reason' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);
        $reason = trim((string) ($data['reason'] ?? $data['decision_reason'] ?? $data['cancel_reason'] ?? $data['note'] ?? ''));

        abort_unless($row->isPending(), 409, 'Only a pending request can be cancelled.');

        $row = DB::transaction(function () use ($row, $request, $reason) {
            $locked = $row->newQuery()->lockForUpdate()->findOrFail($row->id);
            abort_unless($locked->isPending(), 409, 'Only a pending request can be cancelled.');

            $locked->forceFill([
                'status' => PurchaseRequestStatus::Cancelled->value,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_reason' => $reason === '' ? null : $reason,
            ])->save();

            $this->reopenNeeds($locked);

            return $locked->fresh(['lines.stockItem', 'requestedBy', 'decidedBy', 'order']);
        });

        $notifier->requestStatus($gym, $row, $request->user(), "Purchase request {$row->number} was cancelled.");

        return response()->json(['request' => $this->requestPayload($row)]);
    }

    private function decisionValue(?string $value): ?string
    {
        return match ($value) {
            'approved', 'approve' => PurchaseRequestStatus::Approved->value,
            'rejected', 'reject' => PurchaseRequestStatus::Rejected->value,
            default => null,
        };
    }

    private function reopenNeeds(PurchaseRequest $request): void
    {
        $ids = $request->lines()->whereNotNull('supply_need_id')->pluck('supply_need_id')->all();

        if ($ids !== []) {
            SupplyNeed::query()->whereIn('id', $ids)->update(['status' => SupplyNeedStatus::Open->value]);
        }
    }
}
