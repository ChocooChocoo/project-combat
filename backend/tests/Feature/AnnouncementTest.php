<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\CustomerGroup;
use App\Models\Gym;
use App\Models\Room;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 09:00:00'));
        $this->setUpCustomerGym();
        $this->actingAs($this->owner);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Training update', 'body' => 'Please bring your wraps.',
            'kind' => 'announcement', 'audience_type' => 'all_members',
        ], $overrides);
    }

    private function athlete(string $status = 'active'): User
    {
        $user = User::factory()->create();
        $this->gym->addPerson($user, GymPersonType::Member);
        $this->membershipFor($user, ['status' => $status]);

        return $user;
    }

    private function trainingSession(array $overrides = []): TrainingSession
    {
        app(CurrentGym::class)->set($this->gym);

        return TrainingSession::create(array_replace([
            'name' => 'Boxing basics', 'type' => 'classes', 'discipline' => 'boxing', 'level' => 'all',
            'coach_id' => $this->employee->id, 'room_id' => Room::factory()->create()->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(),
            'capacity' => 12, 'status' => 'scheduled',
        ], $overrides));
    }

    private function booking(TrainingSession $session, User $user, string $status): void
    {
        (new SessionBooking)->forceFill([
            'training_session_id' => $session->id, 'user_id' => $user->id,
            'membership_id' => DB::table('memberships')->where('user_id', $user->id)->value('id'),
            'status' => $status,
        ])->save();
    }

    public function test_an_announcement_to_all_members_reaches_only_active_and_frozen_memberships(): void
    {
        $active = $this->athlete();
        $frozen = $this->athlete('frozen');
        $excluded = collect(['expired', 'cancelled', 'applied', 'awaiting_payment'])
            ->map(fn ($status) => $this->athlete($status));
        $this->membershipFor($active, ['status' => 'active']);
        $otherGym = Gym::factory()->approved()->create();
        $otherUser = User::factory()->create();
        app(CurrentGym::class)->set($otherGym);
        $this->membershipFor($otherUser, ['status' => 'active']);
        DB::table('memberships')->where('user_id', $otherUser->id)->update(['gym_id' => $otherGym->id]);

        $this->postJson($this->url('announcements'), $this->payload())
            ->assertCreated()->assertJsonPath('announcement.recipients_count', 2);
        foreach ([$active, $frozen] as $user) {
            $this->assertSame(1, $user->notifications()->count());
        }
        foreach ([...$excluded, $this->employee, $this->member, $this->owner, $otherUser] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_a_class_announcement_reaches_only_booked_athletes(): void
    {
        $session = $this->trainingSession();
        $users = [];
        foreach (['booked', 'requested', 'waitlisted', 'cancelled'] as $status) {
            $users[$status] = $this->athlete();
            $this->booking($session, $users[$status], $status);
        }
        $this->postJson($this->url('announcements'), $this->payload([
            'audience_type' => 'session', 'training_session_id' => $session->id,
        ]))->assertCreated()->assertJsonPath('announcement.recipients_count', 1);
        foreach ($users as $status => $user) {
            $this->assertSame($status === 'booked' ? 1 : 0, $user->notifications()->count());
        }
        $this->assertSame(0, $this->employee->notifications()->count());
    }

    public function test_a_customer_group_announcement_skips_customers_without_accounts(): void
    {
        $linked = $this->addCustomer(['user_id' => $this->member->id]);
        $unlinked = $this->addCustomer(['email' => 'no.account@example.test']);
        $group = CustomerGroup::create(['name' => 'Beginners']);
        $group->members()->attach([$linked->id, $unlinked->id]);
        $this->postJson($this->url('announcements'), $this->payload([
            'audience_type' => 'customer_group', 'customer_group_id' => $group->id,
        ]))->assertCreated()->assertJsonPath('announcement.recipients_count', 1)
            ->assertJsonPath('announcement.skipped_count', 1);
        $this->assertSame(1, $this->member->notifications()->count());
        $this->assertSame(0, $this->employee->notifications()->count());
    }

    public function test_an_event_requires_a_date_and_a_place(): void
    {
        $this->athlete();
        $this->postJson($this->url('announcements'), $this->payload(['kind' => 'event']))
            ->assertUnprocessable()->assertJsonValidationErrors(['event_starts_at', 'event_place']);
        $this->postJson($this->url('announcements'), $this->payload([
            'kind' => 'event', 'event_starts_at' => now()->subDay()->toDateTimeString(), 'event_place' => 'Main hall',
        ]))->assertUnprocessable()->assertJsonValidationErrors('event_starts_at');
        $this->postJson($this->url('announcements'), $this->payload([
            'kind' => 'event', 'event_starts_at' => '2026-11-08 10:00:00', 'event_place' => 'Main hall',
        ]))->assertCreated()->assertJsonPath('announcement.event_place', 'Main hall');
        $data = DB::table('notifications')->first();
        $message = json_decode($data->data, true)['message'];
        $this->assertStringContainsString('Main hall', $message);
        $this->assertStringContainsString('2026', $message);
    }

    public function test_a_session_or_group_from_another_gym_is_rejected(): void
    {
        $otherGym = Gym::factory()->approved()->create();
        $session = $this->trainingSession();
        $group = CustomerGroup::create(['name' => 'Other gym']);
        DB::table('training_sessions')->where('id', $session->id)->update(['gym_id' => $otherGym->id]);
        DB::table('customer_groups')->where('id', $group->id)->update(['gym_id' => $otherGym->id]);
        foreach ([['session', 'training_session_id', $session->id], ['customer_group', 'customer_group_id', $group->id]] as [$type, $key, $id]) {
            $this->postJson($this->url('announcements'), $this->payload(['audience_type' => $type, $key => $id]))->assertNotFound();
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_an_empty_audience_is_rejected(): void
    {
        $this->postJson($this->url('announcements'), $this->payload())->assertUnprocessable()
            ->assertJsonPath('message', 'This audience has no one to notify.');
        $session = $this->trainingSession();
        $group = CustomerGroup::create(['name' => 'Empty group']);
        foreach ([['session', 'training_session_id', $session->id], ['customer_group', 'customer_group_id', $group->id]] as [$type, $key, $id]) {
            $this->postJson($this->url('announcements'), $this->payload(['audience_type' => $type, $key => $id]))
                ->assertUnprocessable()->assertJsonPath('message', 'This audience has no one to notify.');
        }
        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_composer_fields_and_audience_ids_are_validated(): void
    {
        $this->postJson($this->url('announcements'), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body', 'kind', 'audience_type']);
        $this->postJson($this->url('announcements'), $this->payload([
            'title' => str_repeat('x', 121), 'body' => str_repeat('x', 2001),
            'kind' => 'event', 'event_place' => str_repeat('x', 181), 'event_starts_at' => 'not a date',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['title', 'body', 'event_place', 'event_starts_at']);
        $this->postJson($this->url('announcements'), $this->payload(['audience_type' => 'session']))
            ->assertUnprocessable()->assertJsonValidationErrors('training_session_id');
        $this->postJson($this->url('announcements'), $this->payload(['audience_type' => 'customer_group']))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_group_id');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_list_and_send_permissions_are_separate(): void
    {
        $viewer = $this->employeeWithRole(['announcements.general.view']);
        $manager = $this->employeeWithRole(['announcements.general.manage']);
        $this->actingAs($viewer)->getJson($this->url('announcements'))->assertOk();
        $this->postJson($this->url('announcements'), $this->payload())->assertForbidden();
        $this->actingAs($this->employee)->getJson($this->url('announcements'))->assertForbidden();
        $this->athlete();
        $this->actingAs($manager)->postJson($this->url('announcements'), $this->payload())->assertCreated();
    }

    public function test_recipient_counts_are_stored_on_the_announcement(): void
    {
        $this->athlete();
        $id = $this->postJson($this->url('announcements'), $this->payload([
            'training_session_id' => 99999, 'customer_group_id' => 99999,
        ]))->assertCreated()->json('announcement.id');
        $this->assertDatabaseHas('announcements', [
            'id' => $id, 'recipients_count' => 1, 'skipped_count' => 0,
            'training_session_id' => null, 'customer_group_id' => null,
        ]);
    }

    public function test_picker_options_need_manage_and_return_upcoming_sessions_and_groups(): void
    {
        $active = $this->athlete();
        $this->membershipFor($active, ['status' => 'active']);
        $this->athlete('frozen');
        $this->athlete('expired');
        $session = $this->trainingSession();
        $this->booking($session, $active, 'booked');
        $this->booking($session, $this->athlete('applied'), 'requested');
        $this->trainingSession(['status' => 'cancelled']);
        $this->trainingSession(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);
        $this->trainingSession(['starts_at' => now()->addDays(91), 'ends_at' => now()->addDays(91)->addHour()]);
        $linked = $this->addCustomer(['user_id' => $this->member->id]);
        $unlinked = $this->addCustomer(['email' => 'picker.unlinked@example.test']);
        $group = CustomerGroup::create(['name' => 'Picker group']);
        $group->members()->attach([$linked->id, $unlinked->id]);
        $otherGym = Gym::factory()->approved()->create();
        $otherSession = $this->trainingSession();
        $otherGroup = CustomerGroup::create(['name' => 'Not visible']);
        DB::table('training_sessions')->where('id', $otherSession->id)->update(['gym_id' => $otherGym->id]);
        DB::table('customer_groups')->where('id', $otherGroup->id)->update(['gym_id' => $otherGym->id]);

        $viewer = $this->employeeWithRole(['announcements.general.view']);
        $manager = $this->employeeWithRole(['announcements.general.manage']);
        $this->actingAs($this->employee)->getJson($this->url('announcements/options'))->assertForbidden();
        $this->actingAs($viewer)->getJson($this->url('announcements/options'))->assertForbidden();
        $response = $this->actingAs($manager)->getJson($this->url('announcements/options'))->assertOk()
            ->assertJsonPath('member_count', 2)
            ->assertJsonCount(1, 'sessions')->assertJsonPath('sessions.0.id', $session->id)
            ->assertJsonPath('sessions.0.booked_count', 1)
            ->assertJsonCount(1, 'groups')->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.members_count', 2);
        $this->assertEqualsCanonicalizing(['id', 'name', 'starts_at', 'ends_at', 'room_name', 'booked_count'], array_keys($response->json('sessions.0')));
        $this->assertEqualsCanonicalizing(['id', 'name', 'members_count'], array_keys($response->json('groups.0')));
        $this->getJson($this->url('sessions'))->assertForbidden();
        $this->getJson($this->url('customer-groups'))->assertForbidden();
    }

    public function test_the_notification_carries_a_link_to_the_target(): void
    {
        $user = $this->athlete();
        $session = $this->trainingSession();
        $this->booking($session, $user, 'booked');
        foreach (['all_members' => "/gyms/{$this->gym->id}", 'session' => "/gyms/{$this->gym->id}/sessions/{$session->id}"] as $type => $url) {
            $id = $this->postJson($this->url('announcements'), $this->payload([
                'audience_type' => $type, 'training_session_id' => $session->id,
            ]))->assertCreated()->json('announcement.id');
            $data = $user->notifications()->get()->first(fn ($notification) => $notification->data['announcement_id'] === $id)->data;
            $this->assertSame($url, $data['url']);
            $this->assertSame($this->gym->id, $data['gym_id']);
        }
    }

    public function test_cancelled_and_ended_sessions_cannot_be_targeted(): void
    {
        foreach ([['status' => 'cancelled'], ['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]] as $overrides) {
            $session = $this->trainingSession($overrides);
            $this->postJson($this->url('announcements'), $this->payload([
                'audience_type' => 'session', 'training_session_id' => $session->id,
            ]))->assertUnprocessable()->assertJsonValidationErrors('training_session_id');
        }
    }

    public function test_list_is_scoped_filtered_and_paginated_newest_first(): void
    {
        $this->athlete();
        for ($i = 1; $i <= 21; $i++) {
            $this->postJson($this->url('announcements'), $this->payload(['title' => "Update {$i}"]))->assertCreated();
        }
        $this->getJson($this->url('announcements'))->assertOk()->assertJsonCount(20, 'announcements')
            ->assertJsonPath('announcements.0.title', 'Update 21')->assertJsonPath('meta.total', 21);
        $this->getJson($this->url('announcements?page=2'))->assertOk()->assertJsonCount(1, 'announcements');
        $this->getJson($this->url('announcements?audience_type=session'))->assertOk()->assertJsonCount(0, 'announcements');
        $other = Gym::factory()->approved()->create();
        $other->addPerson($this->owner, GymPersonType::Owner);
        $this->getJson("/api/gyms/{$other->id}/announcements")->assertOk()->assertJsonCount(0, 'announcements');
    }
}
