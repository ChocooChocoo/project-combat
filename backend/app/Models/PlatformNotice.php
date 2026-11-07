<?php

namespace App\Models;

use App\Enums\PlatformNoticeAudience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-81: platform-wide sent record, deliberately outside gym tenancy. */
#[Fillable(['title', 'body', 'audience', 'recipients_count', 'created_by'])]
class PlatformNotice extends Model
{
    protected function casts(): array
    {
        return ['audience' => PlatformNoticeAudience::class, 'recipients_count' => 'integer'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
