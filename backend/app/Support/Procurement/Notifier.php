<?php

namespace App\Support\Procurement;

use App\Models\Gym;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\PurchaseRequestDecided;
use App\Notifications\PurchaseStatusChanged;
use Illuminate\Support\Collection;

/** Keeps the R-79 audience rule in one place: requester, approver and creator, minus the actor. */
class Notifier
{
    public function requestDecided(Gym $gym, PurchaseRequest $request): void
    {
        $requester = $request->requestedBy;

        if ($requester) {
            $requester->notify(new PurchaseRequestDecided($gym, $request));
        }
    }

    public function requestStatus(Gym $gym, PurchaseRequest $request, User $actor, string $message): void
    {
        $this->send(
            $gym,
            collect([$request->requested_by, $request->decided_by]),
            $actor,
            new PurchaseStatusChanged(
                $gym,
                $request->number,
                $request->status->value,
                $message,
                null,
                $request->id,
            ),
        );
    }

    public function orderStatus(Gym $gym, PurchaseOrder $order, User $actor, string $message): void
    {
        $request = $order->purchaseRequest;

        $this->send(
            $gym,
            collect([$request?->requested_by, $request?->decided_by, $order->ordered_by]),
            $actor,
            new PurchaseStatusChanged(
                $gym,
                $order->number,
                $order->status->value,
                $message,
                $order->id,
                $request?->id,
            ),
        );
    }

    /** @param Collection<int, int|null> $ids */
    private function send(Gym $gym, Collection $ids, User $actor, PurchaseStatusChanged $notification): void
    {
        $recipients = $ids->filter()->unique()->reject(fn ($id) => (int) $id === (int) $actor->id)->values();

        if ($recipients->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $recipients)->get()
            ->each(fn (User $user) => $user->notify($notification));
    }
}
