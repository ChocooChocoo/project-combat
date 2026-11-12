<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\EquipmentCheck;
use App\Models\Gym;
use App\Models\StockItem;
use App\Models\User;
use App\Notifications\GymNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * R-120: safety checks on equipment and protective gear. A check sets the item's safety status and next due
 * date. Unsafe items alert the owner and stock managers. Notice only: unsafe items stay usable in stock flows.
 */
class EquipmentSafetyController extends Controller
{
    public const CATEGORIES = ['equipment', 'protective_gear'];

    public function index(Gym $gym): JsonResponse
    {
        $items = StockItem::whereIn('category', self::CATEGORIES)->where('status', 'active')
            ->orderByRaw("case when safety_status = 'unsafe' then 0 when safety_status = 'needs_repair' then 1 else 2 end")
            ->orderBy('next_check_on')->orderBy('name')->get();
        $checks = EquipmentCheck::with('checkedBy')->whereIn('stock_item_id', $items->pluck('id'))
            ->orderByDesc('checked_on')->orderByDesc('id')->get()->groupBy('stock_item_id');

        return response()->json(['items' => $items->map(fn (StockItem $item) => $this->itemPayload($item, $checks->get($item->id, collect())->take(5)))->values()]);
    }

    public function updateSchedule(Request $request, Gym $gym, int $item): JsonResponse
    {
        $model = $this->item($item);
        $data = $request->validate(['check_interval_days' => ['nullable', 'integer', 'between:1,365']]);
        $model->check_interval_days = $data['check_interval_days'];
        $model->next_check_on = $data['check_interval_days'] ? ($model->next_check_on ?? today()->addDays($data['check_interval_days'])) : null;
        $model->save();

        return response()->json(['item' => $this->itemPayload($model, collect())]);
    }

    public function check(Request $request, Gym $gym, int $item): JsonResponse
    {
        $model = $this->item($item);
        $data = $request->validate([
            'result' => ['required', Rule::in(['pass', 'needs_repair', 'unsafe'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('result') !== 'pass')],
            'checked_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], ['note.required' => 'Say what is wrong.']);
        $user = $request->user();
        $wasUnsafe = $model->safety_status === 'unsafe';

        DB::transaction(function () use ($model, $data, $user) {
            $on = $data['checked_on'] ?? today()->toDateString();
            $check = new EquipmentCheck;
            $check->forceFill(['stock_item_id' => $model->id, 'result' => $data['result'], 'note' => $data['note'] ?? null, 'checked_by' => $user->id, 'checked_on' => $on])->save();
            $model->safety_status = $data['result'] === 'pass' ? 'ok' : $data['result'];
            $model->next_check_on = $model->check_interval_days ? Carbon::parse($on)->addDays($model->check_interval_days)->toDateString() : null;
            $model->save();
        });

        if ($data['result'] === 'unsafe' && ! $wasUnsafe) {
            $gym->peopleWithPermission('inventory.items.manage')->each(fn (User $person) => $person->notify(new GymNotice(
                'Unsafe equipment',
                "{$model->name} was marked unsafe: {$data['note']}",
                $gym->id,
                "/gyms/{$gym->id}/manage/stock?tab=safety",
            )));
        }

        $checks = EquipmentCheck::with('checkedBy')->where('stock_item_id', $model->id)->orderByDesc('checked_on')->orderByDesc('id')->limit(5)->get();

        return response()->json(['item' => $this->itemPayload($model->fresh(), $checks)]);
    }

    private function item(int $id): StockItem
    {
        return StockItem::whereIn('category', self::CATEGORIES)->findOrFail($id);
    }

    private function itemPayload(StockItem $item, $checks): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'location' => $item->location,
            'quantity' => $item->quantity,
            'safety_status' => $item->safety_status ?? 'ok',
            'check_interval_days' => $item->check_interval_days,
            'next_check_on' => $item->next_check_on ? substr((string) $item->next_check_on, 0, 10) : null,
            'checks' => collect($checks)->map(fn (EquipmentCheck $check) => [
                'id' => $check->id, 'result' => $check->result, 'note' => $check->note, 'checked_on' => $check->checked_on?->toDateString(),
                'by' => $check->checkedBy ? trim("{$check->checkedBy->first_name} {$check->checkedBy->last_name}") : null,
            ])->values(),
        ];
    }
}
