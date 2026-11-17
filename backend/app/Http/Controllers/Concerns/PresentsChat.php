<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\GymPersonType;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\User;
use App\Support\Chat;

/** (R-145) Shared response shapes for the chat screens. */
trait PresentsChat
{
    /** @var array<int, list<int>> coach user ids by gym, loaded once per request */
    private array $chatCoachIds = [];

    /** Owner, Coach, Staff or Member. A coach is an employee with a coach profile. */
    protected function chatRoleLabel(User $person, ?Gym $gym): string
    {
        if (! $gym) {
            return 'Member';
        }
        if ($person->hasGymRole($gym, GymPersonType::Owner)) {
            return 'Owner';
        }
        if ($person->hasGymRole($gym, GymPersonType::Employee)) {
            return in_array($person->id, $this->chatCoachIds($gym), true) ? 'Coach' : 'Staff';
        }

        return 'Member';
    }

    /** One person the viewer may chat with, and the thread they already have: an entry of GET chat/partners. */
    protected function chatPartnerPayload(User $person, ?Gym $gym, ?int $conversationId, int $unreadCount): array
    {
        return [
            'id' => $person->id,
            'name' => $person->fullName(),
            'role_label' => $this->chatRoleLabel($person, $gym),
            'conversation_id' => $conversationId,
            'unread_count' => $unreadCount,
        ];
    }

    /** One thread as the list shows it, from this viewer's side. */
    protected function chatConversationPayload(ChatConversation $conversation, User $viewer, ?Gym $gym): array
    {
        $partner = $conversation->partner($viewer);
        $last = ChatMessage::where('chat_conversation_id', $conversation->id)->orderByDesc('id')->first();

        return [
            'id' => $conversation->id,
            'partner' => [
                'id' => $partner?->id,
                'name' => $partner?->fullName(),
                'role_label' => $partner ? $this->chatRoleLabel($partner, $gym) : null,
            ],
            'last_message' => $last ? [
                'body' => $last->body,
                'sent_at' => $last->created_at?->toIso8601String(),
                'from_me' => $last->sender_id === $viewer->id,
            ] : null,
            'unread_count' => Chat::unreadCount($conversation, $viewer),
            'updated_at' => ($conversation->last_message_at ?? $conversation->updated_at)?->toIso8601String(),
        ];
    }

    /** One message, from this viewer's side. */
    protected function chatMessagePayload(ChatMessage $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'from_me' => $message->sender_id === $viewer->id,
            'sender_name' => $message->sender?->fullName(),
            'sent_at' => $message->created_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
        ];
    }

    /** Ids of the gym's employees who hold a coach profile. One query per request, however long the list. */
    private function chatCoachIds(Gym $gym): array
    {
        return $this->chatCoachIds[$gym->id] ??= $gym->employees()->get()
            ->filter(fn (EmployeeProfile $profile) => $profile->isCoach())
            ->map(fn (EmployeeProfile $profile) => (int) $profile->user_id)->all();
    }
}
