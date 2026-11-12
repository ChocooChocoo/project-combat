<?php

namespace App\Support\Reminders;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\StockItem;
use App\Models\User;
use App\Notifications\GymNotice;
use App\Support\CurrentGym;

/**
 * R-120: one digest per gym listing equipment and protective gear due for a safety check.
 * Each due date is told once (check_reminded_for keeps the date already told).
 */
class EquipmentCheckReminders
{
    public function send(): int
    {
        $sent = 0;

        Gym::where('status', GymStatus::Approved)->each(function (Gym $gym) use (&$sent) {
            app(CurrentGym::class)->set($gym);
            $due = StockItem::whereIn('category', ['equipment', 'protective_gear'])->where('status', 'active')
                ->whereNotNull('next_check_on')->whereDate('next_check_on', '<=', today())
                ->where(fn ($query) => $query->whereNull('check_reminded_for')->orWhereColumn('check_reminded_for', '!=', 'next_check_on'))
                ->orderBy('next_check_on')->get();

            if ($due->isEmpty()) {
                return;
            }

            $names = $due->pluck('name');
            $list = $names->take(10)->implode(', ').($names->count() > 10 ? ' and '.($names->count() - 10).' more' : '');
            $gym->peopleWithPermission('inventory.safety.manage')->each(fn (User $person) => $person->notify(new GymNotice(
                $due->count() === 1 ? '1 safety check due' : $due->count().' safety checks due',
                "Check these items: {$list}.",
                $gym->id,
                "/gyms/{$gym->id}/manage/stock?tab=safety",
            )));

            $due->each(fn (StockItem $item) => $item->forceFill(['check_reminded_for' => $item->next_check_on])->save());
            $sent += $due->count();
        });

        return $sent;
    }
}
