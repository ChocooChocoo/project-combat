<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\Gym;
use Illuminate\Notifications\Notification;

/** Tells the assignee that a customer follow-up is theirs (R-69). */
class FollowUpAssigned extends Notification
{
    public function __construct(
        private Gym $gym,
        private CustomerFollowUp $followUp,
        private Customer $customer,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Follow-up assigned',
            'message' => "Follow up with {$this->customer->name} by "
                .$this->followUp->due_at->format('j M Y, g:i A').": {$this->followUp->note}",
            'gym_id' => $this->gym->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'follow_up_id' => $this->followUp->id,
            'due_at' => $this->followUp->due_at->toIso8601String(),
            'note' => $this->followUp->note,
        ];
    }
}
