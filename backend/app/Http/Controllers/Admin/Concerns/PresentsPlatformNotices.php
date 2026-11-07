<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\PlatformNotice;

trait PresentsPlatformNotices
{
    protected function noticePayload(PlatformNotice $notice): array
    {
        return [
            'id' => $notice->id, 'title' => $notice->title, 'body' => $notice->body,
            'audience' => $notice->audience->value, 'recipients_count' => $notice->recipients_count,
            'created_by' => $notice->created_by,
            'created_at' => $notice->created_at?->toIso8601String(),
        ];
    }
}
