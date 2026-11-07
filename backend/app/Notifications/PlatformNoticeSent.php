<?php

namespace App\Notifications;

use App\Models\PlatformNotice;
use Illuminate\Notifications\Notification;

class PlatformNoticeSent extends Notification
{
    public function __construct(private PlatformNotice $notice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->notice->title, 'message' => $this->notice->body, 'notice_id' => $this->notice->id];
    }
}
