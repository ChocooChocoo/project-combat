<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\PurchaseRequest;
use Illuminate\Notifications\Notification;

/** R-76: the requester is told what the permitted approver decided. */
class PurchaseRequestDecided extends Notification
{
    public function __construct(
        private Gym $gym,
        private PurchaseRequest $purchaseRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status = $this->purchaseRequest->status->value;
        $reason = trim((string) $this->purchaseRequest->decision_reason);

        return [
            'title' => "Purchase request {$status}",
            'message' => "Purchase request {$this->purchaseRequest->number} was {$status}."
                .($reason === '' ? '' : " Reason: {$reason}"),
            'gym_id' => $this->gym->id,
            'purchase_request_id' => $this->purchaseRequest->id,
            'number' => $this->purchaseRequest->number,
            'status' => $status,
            'decision_reason' => $reason === '' ? null : $reason,
        ];
    }
}
