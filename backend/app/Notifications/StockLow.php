<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\StockItem;
use Illuminate\Notifications\Notification;

class StockLow extends Notification
{
    public function __construct(private Gym $gym, private StockItem $item) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Low stock',
            'message' => "{$this->item->name} is down to {$this->item->quantity} {$this->item->unitLabel($this->item->quantity)} (minimum {$this->item->min_level}) at {$this->item->location}.",
            'gym_id' => $this->gym->id, 'stock_item_id' => $this->item->id,
            'item_name' => $this->item->name, 'quantity' => $this->item->quantity,
            'min_level' => $this->item->min_level, 'location' => $this->item->location,
        ];
    }
}
