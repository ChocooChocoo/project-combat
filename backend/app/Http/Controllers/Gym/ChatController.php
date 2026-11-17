<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Concerns\PresentsChat;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Gym;
use App\Models\User;
use App\Support\Chat;
use App\Support\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * (R-145) Chat inside one gym: a member with the staff and the owner, staff and the owner with everyone.
 * A viewer who is not one of the two people in a thread gets 404, never 403, so a thread's existence is
 * not revealed. The rules themselves live in App\Support\Chat.
 */
class ChatController extends Controller
{
    use PresentsChat;

    /** This person's threads, most recent activity first. */
    public function index(Request $request, Gym $gym): JsonResponse
    {
        $viewer = $request->user();

        $rows = ChatConversation::with(['userOne', 'userTwo'])
            ->where(fn ($query) => $query->where('user_one_id', $viewer->id)->orWhere('user_two_id', $viewer->id))
            ->orderByDesc('last_message_at')->orderByDesc('id')->get();

        return response()->json([
            'conversations' => $rows->map(fn (ChatConversation $row) => $this->chatConversationPayload($row, $viewer, $gym))->values(),
        ]);
    }

    /** Everyone this person may chat with, with the thread they already have. */
    public function partners(Request $request, Gym $gym): JsonResponse
    {
        $viewer = $request->user();

        return response()->json([
            'partners' => Chat::partnersFor($viewer, $gym)
                ->map(fn (array $row) => $this->chatPartnerPayload($row['user'], $gym, $row['conversation_id'], $row['unread_count']))
                ->values(),
        ]);
    }

    public function unreadCount(Request $request, Gym $gym): JsonResponse
    {
        return response()->json(['unread_count' => Chat::unreadFor($request->user(), $gym)]);
    }

    /** Starts the thread with one person, or returns the one these two already share. */
    public function store(Request $request, Gym $gym): JsonResponse
    {
        $viewer = $request->user();
        $data = $request->validate(['with_user_id' => ['required', 'integer', 'exists:users,id']]);

        $other = User::findOrFail($data['with_user_id']);
        $conversation = Chat::start($viewer, $other, $gym);

        return response()->json(['conversation' => $this->chatConversationPayload($conversation, $viewer, $gym)], 201);
    }

    /** One thread, newest message first. Older pages come with `?page=`. */
    public function show(Request $request, Gym $gym, int $conversation): JsonResponse
    {
        $viewer = $request->user();
        $row = $this->conversationFor($viewer, $conversation);
        $request->validate(Paging::rules());

        [$messages, $meta] = Paging::rows(
            ChatMessage::with('sender')->where('chat_conversation_id', $row->id)->orderByDesc('id'),
            $request,
        );

        return response()->json([
            'conversation' => $this->chatConversationPayload($row, $viewer, $gym),
            'messages' => $messages->map(fn (ChatMessage $message) => $this->chatMessagePayload($message, $viewer))->values(),
            ...($meta ? ['meta' => $meta] : []),
        ]);
    }

    public function send(Request $request, Gym $gym, int $conversation): JsonResponse
    {
        $viewer = $request->user();
        $row = $this->conversationFor($viewer, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = Chat::send($row, $viewer, $data['body']);

        return response()->json([
            'message' => $this->chatMessagePayload($message->load('sender'), $viewer),
            'conversation' => $this->chatConversationPayload($row->refresh(), $viewer, $gym),
        ], 201);
    }

    /** Opening a thread clears the partner's unread messages. Returns what is left. */
    public function markRead(Request $request, Gym $gym, int $conversation): JsonResponse
    {
        $viewer = $request->user();
        $row = $this->conversationFor($viewer, $conversation);
        Chat::markRead($row, $viewer);

        return response()->json(['unread_count' => Chat::unreadCount($row, $viewer)]);
    }

    /** The thread, when this viewer is one of the two people in it. Anyone else sees 404. */
    private function conversationFor(User $viewer, int $conversation): ChatConversation
    {
        $row = ChatConversation::with(['userOne', 'userTwo'])->findOrFail($conversation);
        abort_unless(in_array($viewer->id, [$row->user_one_id, $row->user_two_id], true), 404);

        return $row;
    }
}
