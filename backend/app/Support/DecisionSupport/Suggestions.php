<?php

namespace App\Support\DecisionSupport;

use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Support\CurrentGym;
use App\Support\Procurement\Restock;

/**
 * (R-141) suggestions only; the owner confirms every action and the target endpoint checks permission.
 * Request actions describe a call to an existing endpoint; nothing is written here.
 */
final class Suggestions
{
    private const MAX_ITEMS = 10;

    public function forGym(Gym $gym): array
    {
        app(CurrentGym::class)->set($gym);
        $items = [];

        $restock = app(Restock::class)->forGym($gym);
        if ($restock->isNotEmpty()) {
            $items[] = [
                'code' => 'low_stock',
                'title' => 'Restock '.$restock->count().($restock->count() === 1 ? ' item' : ' items'),
                'reason' => $restock->take(3)->pluck('name')->implode(', ').($restock->count() > 3 ? ' and more' : ''),
                'action' => ['type' => 'request', 'label' => 'Create purchase request',
                    'confirm' => 'Create a purchase request for these items? It still needs approval before anything is ordered.',
                    'method' => 'POST', 'path' => 'purchase-requests', 'permission' => 'procurement.requests.manage',
                    'body' => ['note' => 'Suggested by Analytics', 'lines' => $restock->map(fn (array $row) => [
                        'stock_item_id' => $row['stock_item_id'],
                        'quantity' => $row['suggested_quantity'],
                        'est_unit_cost' => $row['unit_cost'],
                    ])->values()->all()]],
            ];
        }

        $problems = app(Problems::class)->forPeriod($gym, new Period(today()->subDays(60), today()))['items'];
        foreach ($problems as $problem) {
            if ($problem['code'] === 'falling_attendance') {
                $name = $problem['name'];
                $items[] = [
                    'code' => 'falling_attendance',
                    'title' => "Attendance is falling in {$name}",
                    'reason' => 'Attendance dropped by '.$problem['evidence']['drop_points'].' points against the earlier sessions.',
                    'action' => ['type' => 'request', 'label' => 'Send a notice to members',
                        'confirm' => 'Send this notice to all members of the gym?',
                        'method' => 'POST', 'path' => 'announcements', 'permission' => 'announcements.general.manage',
                        'body' => ['title' => "See you at {$name}", 'body' => "We miss you at {$name}. Book your next session this week.",
                            'kind' => 'announcement', 'audience_type' => 'all_members']],
                ];
            } elseif ($problem['code'] === 'low_use_slot') {
                $items[] = [
                    'code' => 'low_use_slot',
                    'title' => "{$problem['name']} is mostly empty",
                    'reason' => 'Average fill '.$problem['evidence']['average_fill'].'%',
                    'action' => ['type' => 'link', 'label' => 'Review the schedule', 'to' => "/gyms/{$gym->id}/manage/schedule"],
                ];
            }
        }

        $ending = $gym->memberships()->where('status', MembershipStatus::Active->value)
            ->whereDate('ends_on', '>=', today()->toDateString())
            ->whereDate('ends_on', '<=', today()->addDays(7)->toDateString())->count();
        if ($ending > 0) {
            $items[] = [
                'code' => 'expiring_memberships',
                'title' => $ending.($ending === 1 ? ' membership ends' : ' memberships end').' this week',
                'reason' => 'Reach out so they renew.',
                'action' => ['type' => 'link', 'label' => 'Open members', 'to' => "/gyms/{$gym->id}/manage/members"],
            ];
        }

        return ['items' => array_slice($items, 0, self::MAX_ITEMS)];
    }
}
