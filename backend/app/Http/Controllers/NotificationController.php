<?php

namespace App\Http\Controllers;

use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-10: each person's own notification inbox. Notices are added by later features. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(DateRange::rules());

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => DateRange::apply($user->notifications(), $data, 'created_at')->latest()->paginate(20),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked as read.']);
    }

    public function clearRead(Request $request): JsonResponse
    {
        $request->user()->readNotifications()->delete();

        return response()->json(['message' => 'Read notices cleared.']);
    }
}
