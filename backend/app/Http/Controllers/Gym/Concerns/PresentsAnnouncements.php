<?php

namespace App\Http\Controllers\Gym\Concerns;

use App\Models\Announcement;

trait PresentsAnnouncements
{
    protected function announcementPayload(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id, 'title' => $announcement->title, 'body' => $announcement->body,
            'kind' => $announcement->kind->value, 'audience_type' => $announcement->audience_type->value,
            'training_session_id' => $announcement->training_session_id, 'session_name' => $announcement->session?->name,
            'customer_group_id' => $announcement->customer_group_id, 'group_name' => $announcement->group?->name,
            'event_starts_at' => $announcement->event_starts_at?->toIso8601String(), 'event_place' => $announcement->event_place,
            'recipients_count' => $announcement->recipients_count, 'skipped_count' => $announcement->skipped_count,
            'created_by' => $announcement->created_by,
            'created_by_name' => $announcement->createdBy ? trim($announcement->createdBy->first_name.' '.$announcement->createdBy->last_name) : null,
            'created_at' => $announcement->created_at?->toIso8601String(),
        ];
    }
}
