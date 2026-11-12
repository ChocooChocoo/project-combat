<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A plain in-app notice: title, message, gym and an optional link.
 * Used by P09 features (offers, coach applications, monitoring, safety) instead of one class per notice.
 */
class GymNotice extends Notification
{
    public function __construct(
        private string $title,
        private string $message,
        private ?int $gymId = null,
        private ?string $url = null,
        private array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_filter([
            'title' => $this->title,
            'message' => $this->message,
            'gym_id' => $this->gymId,
            'url' => $this->url,
            ...$this->extra,
        ], fn ($value) => $value !== null);
    }
}
