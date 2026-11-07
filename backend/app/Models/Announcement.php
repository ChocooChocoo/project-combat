<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementKind;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-80: immutable sent announcement and its delivery counts. */
#[Fillable([
    'title', 'body', 'kind', 'event_starts_at', 'event_place', 'audience_type',
    'training_session_id', 'customer_group_id', 'recipients_count', 'skipped_count', 'created_by',
])]
class Announcement extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'kind' => AnnouncementKind::class, 'audience_type' => AnnouncementAudience::class,
            'event_starts_at' => 'datetime', 'recipients_count' => 'integer', 'skipped_count' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
