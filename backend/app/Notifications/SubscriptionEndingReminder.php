<?php

namespace App\Notifications;

use App\Models\Gym;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** R-107: the owner hears before the trial or paid month ends. */
class SubscriptionEndingReminder extends Notification
{
    public function __construct(private Gym $gym, private string $status, private Carbon $endsAt) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $what = $this->status === 'trial' ? 'free trial' : 'plan';

        return [
            'title' => $this->status === 'trial' ? 'Free trial ends soon' : 'Plan renewal due soon',
            'message' => "{$this->gym->name}'s {$what} ends on {$this->endsAt->format('j M Y')}. "
                .'Subscribe to keep every module you use; without it the gym moves to the free plan.',
            'gym_id' => $this->gym->id,
            'ends_at' => $this->endsAt->toIso8601String(),
            'url' => "/gyms/{$this->gym->id}/manage/subscription",
        ];
    }
}
