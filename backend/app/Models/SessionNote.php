<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-55: participant notes stay scoped to their gym and athlete. */
class SessionNote extends Model
{
    use BelongsToGym;

    protected $guarded = ['id'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function payload(): array
    {
        return [
            'id' => $this->id, 'user_id' => $this->user_id, 'body' => $this->body,
            'date' => $this->session->starts_at->toDateString(),
            'session' => ['id' => $this->training_session_id, 'name' => $this->session->name],
            'author' => trim("{$this->author->first_name} {$this->author->last_name}"),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
