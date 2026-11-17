<?php

namespace App\Support;

use App\Enums\GymPersonType;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * (R-145) Chat inside one gym. A member talks to the staff and the owner; staff and the owner talk to
 * everyone; a member never messages another member. All the chat rules live here: who may start a
 * thread, what counts as unread, and which people the picker offers.
 */
final class Chat
{
    public const BODY_LIMIT = 2000;

    /** Whether these two people may chat in this gym: at least one of them must own it or work there. */
    public static function canChat(User $a, User $b, Gym $gym): bool
    {
        if ($a->id === $b->id || ! $a->hasGymRole($gym) || ! $b->hasGymRole($gym)) {
            return false;
        }

        return self::isStaff($a, $gym) || self::isStaff($b, $gym);
    }

    /** The one thread these two people share in this gym, made now or found again. */
    public static function start(User $me, User $other, Gym $gym): ChatConversation
    {
        if (! self::canChat($me, $other, $gym)) {
            abort(422, self::refusal($me, $other, $gym));
        }

        [$userOne, $userTwo] = ChatConversation::ordered($me->id, $other->id);

        return ChatConversation::firstOrCreate(['user_one_id' => $userOne, 'user_two_id' => $userTwo]);
    }

    /** Stores one message and moves the thread to the top of every list. */
    public static function send(ChatConversation $conversation, User $sender, string $body): ChatMessage
    {
        $text = trim($body);
        abort_if($text === '' || mb_strlen($text) > self::BODY_LIMIT, 422, 'Write a message of up to 2,000 characters.');

        $message = new ChatMessage;
        $message->chat_conversation_id = $conversation->id;
        $message->sender_id = $sender->id;
        $message->body = $text;
        $message->save();

        $conversation->last_message_at = $message->created_at ?? now();
        $conversation->save();

        return $message;
    }

    /** Marks the partner's messages as read. Returns how many were still unread. */
    public static function markRead(ChatConversation $conversation, User $reader): int
    {
        return ChatMessage::where('chat_conversation_id', $conversation->id)
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** Everything written to this person in this gym that they have not opened yet. */
    public static function unreadFor(User $user, Gym $gym): int
    {
        return ChatMessage::whereIn('chat_conversation_id', self::threads($user, $gym)->modelKeys())
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /** The same count for one thread, as the conversation list shows it. */
    public static function unreadCount(ChatConversation $conversation, User $viewer): int
    {
        return ChatMessage::where('chat_conversation_id', $conversation->id)
            ->where('sender_id', '!=', $viewer->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Everyone in the gym this person may chat with, with the thread they already have: a member reaches
     * the owner and the staff, staff and the owner reach everyone else.
     *
     * @return Collection<int, array{user: User, conversation_id: ?int, unread_count: int}>
     */
    public static function partnersFor(User $user, Gym $gym): Collection
    {
        $staffIds = $gym->people()
            ->wherePivotIn('role', [GymPersonType::Owner->value, GymPersonType::Employee->value])
            ->get()->modelKeys();
        $viewerIsStaff = in_array($user->id, $staffIds, true);

        $partners = $gym->people()->orderBy('first_name')->orderBy('last_name')->get()->unique('id')
            ->reject(fn (User $person) => $person->id === $user->id)
            ->filter(fn (User $person) => $viewerIsStaff || in_array($person->id, $staffIds, true))
            ->values();

        $threads = self::threads($user, $gym);
        $unread = ChatMessage::whereIn('chat_conversation_id', $threads->modelKeys())
            ->where('sender_id', '!=', $user->id)->whereNull('read_at')
            ->selectRaw('chat_conversation_id, count(*) as total')
            ->groupBy('chat_conversation_id')->pluck('total', 'chat_conversation_id');

        $rows = [];
        foreach ($partners as $person) {
            $thread = $threads->first(fn (ChatConversation $row) => $row->partnerId($user->id) === $person->id);

            $rows[] = [
                'user' => $person,
                'conversation_id' => $thread?->id,
                'unread_count' => $thread ? (int) ($unread[$thread->id] ?? 0) : 0,
            ];
        }

        return collect($rows);
    }

    /** Why a start was refused, in plain words. */
    private static function refusal(User $me, User $other, Gym $gym): string
    {
        $bothMembers = $me->id !== $other->id
            && $me->hasGymRole($gym, GymPersonType::Member)
            && $other->hasGymRole($gym, GymPersonType::Member);

        return $bothMembers
            ? 'Members cannot message other members. Message the gym staff instead.'
            : 'You cannot start a chat with this person.';
    }

    /** This gym's threads the person is one of the two people in. */
    private static function threads(User $user, Gym $gym): Collection
    {
        return ChatConversation::where('gym_id', $gym->id)
            ->where(fn ($query) => $query->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))
            ->get();
    }

    private static function isStaff(User $user, Gym $gym): bool
    {
        return $user->hasGymRole($gym, GymPersonType::Owner, GymPersonType::Employee);
    }
}
