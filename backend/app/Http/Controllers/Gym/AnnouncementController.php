<?php

namespace App\Http\Controllers\Gym;

use App\Enums\AnnouncementAudience as AudienceType;
use App\Enums\AnnouncementKind;
use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsAnnouncements;
use App\Models\Announcement;
use App\Models\CustomerGroup;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\TrainingSession;
use App\Notifications\AnnouncementPublished;
use App\Support\Announcements\AnnouncementAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/** R-80: targeted announcements are sent once, synchronously. */
class AnnouncementController extends Controller
{
    use PresentsAnnouncements;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate(['audience_type' => ['sometimes', Rule::enum(AudienceType::class)]]);
        $page = Announcement::where('gym_id', $gym->id)->with(['session', 'group', 'createdBy'])
            ->when($data['audience_type'] ?? null, fn ($query, $type) => $query->where('audience_type', $type))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return response()->json([
            'announcements' => collect($page->items())->map(fn ($announcement) => $this->announcementPayload($announcement)),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }

    public function options(Gym $gym): JsonResponse
    {
        $sessions = TrainingSession::where('gym_id', $gym->id)->where('status', SessionStatus::Scheduled)
            ->whereBetween('starts_at', [now(), now()->addDays(90)])
            ->with('room:id,name')->withCount(['bookings as booked_count' => fn ($query) => $query->where('status', BookingStatus::Booked)])
            ->orderBy('starts_at')->orderBy('id')->get(['id', 'name', 'starts_at', 'ends_at', 'room_id']);

        return response()->json([
            'member_count' => Membership::where('gym_id', $gym->id)
                ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Frozen])
                ->whereNotNull('user_id')->distinct()->count('user_id'),
            'sessions' => $sessions->map(fn ($session) => [
                'id' => $session->id, 'name' => $session->name,
                'starts_at' => $session->starts_at->toIso8601String(), 'ends_at' => $session->ends_at->toIso8601String(),
                'room_name' => $session->room?->name, 'booked_count' => $session->booked_count,
            ]),
            'groups' => CustomerGroup::where('gym_id', $gym->id)->select(['id', 'name'])->withCount('members')
                ->orderBy('name')->orderBy('id')->get()->map(fn ($group) => [
                    'id' => $group->id, 'name' => $group->name, 'members_count' => $group->members_count,
                ]),
        ]);
    }

    public function store(Request $request, Gym $gym, AnnouncementAudience $audiences): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:2000'],
            'kind' => ['required', Rule::enum(AnnouncementKind::class)],
            'event_starts_at' => ['exclude_unless:kind,event', 'required', 'date', 'after:now'],
            'event_place' => ['exclude_unless:kind,event', 'required', 'string', 'max:180'],
            'audience_type' => ['required', Rule::enum(AudienceType::class)],
            'training_session_id' => ['exclude_unless:audience_type,session', 'required', 'integer', 'min:1'],
            'customer_group_id' => ['exclude_unless:audience_type,customer_group', 'required', 'integer', 'min:1'],
        ]);
        $data['training_session_id'] = $data['training_session_id'] ?? null;
        $data['customer_group_id'] = $data['customer_group_id'] ?? null;
        if (isset($data['event_starts_at'])) {
            $data['event_starts_at'] = Carbon::parse($data['event_starts_at'])->setTimezone(config('app.timezone'));
        }

        $announcement = DB::transaction(function () use ($request, $gym, $audiences, $data) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $audience = $audiences->resolve($gym, AudienceType::from($data['audience_type']), $data['training_session_id'], $data['customer_group_id']);
            abort_if($audience['users']->isEmpty(), 422, 'This audience has no one to notify.');
            $announcement = Announcement::create([
                ...$data, 'created_by' => $request->user()->id,
                'recipients_count' => $audience['users']->count(), 'skipped_count' => $audience['skipped'],
            ]);
            foreach ($audience['users']->chunk(200) as $users) {
                Notification::sendNow($users, new AnnouncementPublished($announcement));
            }

            return $announcement;
        });

        return response()->json(['announcement' => $this->announcementPayload($announcement->load(['session', 'group', 'createdBy']))], 201);
    }
}
