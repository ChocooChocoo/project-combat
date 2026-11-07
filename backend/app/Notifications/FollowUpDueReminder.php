<?php

namespace App\Notifications;

use App\Models\CustomerFollowUp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FollowUpDueReminder extends Notification
{
    public function __construct(private CustomerFollowUp $followUp) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Follow-up due',
            'message' => "Follow up with {$this->followUp->customer->name}: ".Str::limit($this->followUp->note, 200),
            'gym_id' => $this->followUp->gym_id, 'follow_up_id' => $this->followUp->id,
            'customer_id' => $this->followUp->customer_id, 'due_at' => $this->followUp->due_at->toIso8601String(),
            'url' => "/gyms/{$this->followUp->gym_id}/manage/customers/{$this->followUp->customer_id}",
        ];
    }
}
