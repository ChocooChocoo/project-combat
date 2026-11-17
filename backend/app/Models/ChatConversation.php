<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** (R-145) One chat thread between two people of the same gym. A pair has exactly one thread. */
#[Fillable(['user_one_id', 'user_two_id', 'last_message_at'])]
class ChatConversation extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** The whole thread, oldest first. */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /** Both ids, lowest first: the stored order, so the same pair always finds the same thread. */
    public static function ordered(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    /** The other person in the thread, as this viewer sees it. */
    public function partner(User $viewer): ?User
    {
        return $this->user_one_id === $viewer->id ? $this->userTwo : $this->userOne;
    }

    /** The other person's id, without loading them. */
    public function partnerId(int $viewerId): int
    {
        return $this->user_one_id === $viewerId ? (int) $this->user_two_id : (int) $this->user_one_id;
    }
}
