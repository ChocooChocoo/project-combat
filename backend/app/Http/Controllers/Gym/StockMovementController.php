<?php

namespace App\Http\Controllers\Gym;

use App\Support\DateRange;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsStock;
use App\Models\Gym;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    use PresentsStock;

    public function index(Request $request, Gym $gym, int $item): JsonResponse
    {
        $data = $request->validate(DateRange::rules());
        $stock = $gym->stockItems()->findOrFail($item);
        $page = DateRange::apply($stock->movements(), $data, 'created_at')->with(['actor', 'issuedTo'])->withSum('returns as returned', 'quantity')
            ->orderByDesc('id')->paginate(30);
        $openIssues = $stock->movements()->where('type', StockMovementType::Issue->value)
            ->with('issuedTo')->withSum('returns as returned', 'quantity')->orderByDesc('id')->get()
            ->filter(fn (StockMovement $movement) => abs($movement->quantity) - (int) $movement->returned > 0)->values();

        return response()->json([
            'item' => $this->itemPayload($stock),
            'movements' => collect($page->items())->map(fn (StockMovement $movement) => $this->movementPayload($movement)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'open_issues' => $openIssues->map(fn (StockMovement $movement) => $this->openIssuePayload($movement)),
        ]);
    }

    public function store(Request $request, Gym $gym, int $item, StockLedger $ledger): JsonResponse
    {
        $stock = $gym->stockItems()->findOrFail($item);
        $data = $request->validate([
            'type' => ['required', Rule::in(StockMovementType::manual())],
            'quantity' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:type,adjustment,damaged,lost'],
            'issued_to_user_id' => ['nullable', 'integer', 'prohibited_unless:type,issue'],
            'returns_movement_id' => ['nullable', 'integer', 'required_if:type,return', 'prohibited_unless:type,return'],
        ]);
        if (isset($data['issued_to_user_id'])) {
            $gym->people()->findOrFail($data['issued_to_user_id']);
        }
        if (isset($data['returns_movement_id'])) {
            $gym->stockMovements()->findOrFail($data['returns_movement_id']);
        }
        $movement = $ledger->move($stock, $data['type'], (int) $data['quantity'], $request->user(), array_filter([
            'note' => $data['note'] ?? null, 'issued_to_user_id' => $data['issued_to_user_id'] ?? null,
            'returns_movement_id' => $data['returns_movement_id'] ?? null,
        ], fn ($value) => $value !== null));
        $movement->load(['actor', 'issuedTo'])->loadSum('returns as returned', 'quantity');

        return response()->json(['movement' => $this->movementPayload($movement), 'item' => $this->itemPayload($stock)], 201);
    }

    public function recipients(Gym $gym): JsonResponse
    {
        $people = $gym->people()->get()->unique('id')->map(fn (User $user) => [
            'id' => $user->id, 'name' => $this->personName($user), 'role' => $user->pivot->role,
        ])->sortBy('name')->values();

        return response()->json(['people' => $people]);
    }
}
