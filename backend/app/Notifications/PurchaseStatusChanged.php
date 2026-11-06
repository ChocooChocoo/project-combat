<?php

namespace App\Notifications;

use App\Models\Gym;
use Illuminate\Notifications\Notification;

/** R-79: a purchase status changed; the people involved hear about it once. */
class PurchaseStatusChanged extends Notification
{
    public function __construct(
        private Gym $gym,
        private string $number,
        private string $status,
        private string $message,
        private ?int $purchaseOrderId = null,
        private ?int $purchaseRequestId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = ucfirst(str_replace('_', ' ', $this->status));
        $subject = $this->purchaseOrderId !== null ? 'Purchase order' : 'Purchase request';

        return [
            'title' => "{$subject} {$label}",
            'message' => $this->message,
            'gym_id' => $this->gym->id,
            'purchase_order_id' => $this->purchaseOrderId,
            'purchase_request_id' => $this->purchaseRequestId,
            'number' => $this->number,
            'status' => $this->status,
        ];
    }
}
