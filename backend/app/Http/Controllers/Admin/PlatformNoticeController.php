<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformNoticeAudience as AudienceType;
use App\Http\Controllers\Admin\Concerns\PresentsPlatformNotices;
use App\Http\Controllers\Controller;
use App\Models\PlatformNotice;
use App\Notifications\PlatformNoticeSent;
use App\Support\PlatformNotices\PlatformNoticeAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class PlatformNoticeController extends Controller
{
    use PresentsPlatformNotices;

    public function index(): JsonResponse
    {
        $page = PlatformNotice::orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return response()->json([
            'notices' => collect($page->items())->map(fn ($notice) => $this->noticePayload($notice)),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request, PlatformNoticeAudience $audiences): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::enum(AudienceType::class)],
        ]);
        $notice = DB::transaction(function () use ($request, $audiences, $data) {
            $recipients = $audiences->resolve(AudienceType::from($data['audience']), $request->user());
            $notice = PlatformNotice::create([
                ...$data, 'created_by' => $request->user()->id, 'recipients_count' => $recipients->count(),
            ]);
            foreach ($recipients->chunk(200) as $users) {
                Notification::sendNow($users, new PlatformNoticeSent($notice));
            }

            return $notice;
        });

        return response()->json(['notice' => $this->noticePayload($notice)], 201);
    }
}
