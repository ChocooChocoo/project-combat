<?php

namespace App\Notifications;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementKind;
use App\Models\Announcement;
use Illuminate\Notifications\Notification;

class AnnouncementPublished extends Notification
{
    public function __construct(private Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $announcement = $this->announcement;
        $message = $announcement->body;
        if ($announcement->kind === AnnouncementKind::Event) {
            $message .= "\n".$announcement->event_starts_at->timezone(config('app.timezone'))->format('M j, Y g:i A').' at '.$announcement->event_place;
        }

        return [
            'title' => $announcement->title, 'message' => $message,
            'gym_id' => $announcement->gym_id, 'announcement_id' => $announcement->id,
            'kind' => $announcement->kind->value, 'event_starts_at' => $announcement->event_starts_at?->toIso8601String(),
            'event_place' => $announcement->event_place,
            'url' => $announcement->audience_type === AnnouncementAudience::Session
                ? "/gyms/{$announcement->gym_id}/sessions/{$announcement->training_session_id}"
                : "/gyms/{$announcement->gym_id}",
        ];
    }
}
