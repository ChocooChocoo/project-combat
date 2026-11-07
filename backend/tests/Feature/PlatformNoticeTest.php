<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\PlatformNoticeSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['title' => 'Platform update', 'body' => 'Please review the latest platform guidance.', 'audience' => 'owners'], $overrides);
    }

    private function owner(string $status = 'approved'): User
    {
        $owner = User::factory()->create();
        Gym::factory()->create(['status' => $status])->addPerson($owner, GymPersonType::Owner);

        return $owner;
    }

    public function test_only_the_platform_administrator_can_list_or_send_notices(): void
    {
        $owner = $this->owner();
        $employee = User::factory()->create();
        Gym::firstOrFail()->addPerson($employee, GymPersonType::Employee);
        foreach ([$owner, $employee, User::factory()->create()] as $user) {
            $this->actingAs($user)->getJson('/api/admin/notices')->assertForbidden();
            $this->postJson('/api/admin/notices', $this->payload())->assertForbidden();
        }
        $this->actingAs($this->admin)->getJson('/api/admin/notices')->assertOk();
        $this->postJson('/api/admin/notices', $this->payload())->assertCreated();
    }

    public function test_the_owners_audience_reaches_one_owner_per_approved_gym(): void
    {
        $first = $this->owner();
        $second = $this->owner();
        Gym::factory()->approved()->create()->addPerson($first, GymPersonType::Owner);
        $staff = User::factory()->create();
        $member = User::factory()->create();
        $gym = Gym::firstOrFail();
        $gym->addPerson($staff, GymPersonType::Employee);
        $gym->addPerson($member, GymPersonType::Member);
        $pending = $this->owner('submitted');
        $rejected = $this->owner('rejected');
        $this->postJson('/api/admin/notices', $this->payload())->assertCreated()->assertJsonPath('notice.recipients_count', 2);
        foreach ([$first, $second] as $user) {
            $this->assertSame(1, $user->notifications()->count());
        }
        foreach ([$staff, $member, $pending, $rejected] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_the_all_users_audience_reaches_only_verified_users(): void
    {
        $owner = $this->owner();
        $employee = User::factory()->create();
        $member = User::factory()->create();
        $gym = Gym::firstOrFail();
        $gym->addPerson($employee, GymPersonType::Employee);
        $gym->addPerson($member, GymPersonType::Member);
        $unverified = User::factory()->unverified()->create();
        $this->postJson('/api/admin/notices', $this->payload(['audience' => 'all_users']))->assertCreated()
            ->assertJsonPath('notice.recipients_count', 3);
        foreach ([$owner, $employee, $member] as $user) {
            $this->assertSame(1, $user->notifications()->count());
        }
        $this->assertSame(0, $unverified->notifications()->count());
    }

    public function test_the_sender_is_not_notified(): void
    {
        Gym::factory()->approved()->create()->addPerson($this->admin, GymPersonType::Owner);
        foreach (['owners', 'all_users'] as $audience) {
            $this->postJson('/api/admin/notices', $this->payload(['audience' => $audience]))->assertCreated()
                ->assertJsonPath('notice.recipients_count', 0);
        }
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_the_notification_carries_no_gym_data(): void
    {
        $owner = $this->owner();
        $id = $this->postJson('/api/admin/notices', $this->payload())->assertCreated()->json('notice.id');
        $notification = $owner->notifications()->firstOrFail();
        $this->assertSame(PlatformNoticeSent::class, $notification->type);
        $this->assertSame(['title' => $this->payload()['title'], 'message' => $this->payload()['body'], 'notice_id' => $id], $notification->data);
        foreach (['gym_id', 'gym_name', 'gym_address', 'address'] as $field) {
            $this->assertArrayNotHasKey($field, $notification->data);
        }
        $this->actingAs($owner)->getJson('/api/notifications')->assertOk()->assertJsonPath('notifications.data.0.data.notice_id', $id);
    }

    public function test_recipient_count_is_stored(): void
    {
        User::factory()->count(201)->create();
        $id = $this->postJson('/api/admin/notices', $this->payload(['audience' => 'all_users']))->assertCreated()->json('notice.id');
        $this->assertDatabaseHas('platform_notices', ['id' => $id, 'recipients_count' => 201, 'created_by' => $this->admin->id]);
        $this->assertDatabaseCount('notifications', 201);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_title_body_and_audience_are_required(): void
    {
        $this->postJson('/api/admin/notices', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'body', 'audience']);
        $this->postJson('/api/admin/notices', $this->payload(['title' => str_repeat('x', 121), 'body' => str_repeat('x', 2001), 'audience' => 'staff']))
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'body', 'audience']);
        $this->assertDatabaseCount('platform_notices', 0);
    }

    public function test_the_history_is_paginated_newest_first(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->postJson('/api/admin/notices', $this->payload(['title' => "Update {$i}"]))->assertCreated();
        }
        DB::table('platform_notices')->where('title', 'Update 1')->update(['created_at' => now()->addDay()]);
        $this->getJson('/api/admin/notices')->assertOk()->assertJsonCount(20, 'notices')
            ->assertJsonPath('notices.0.title', 'Update 1')->assertJsonPath('notices.1.title', 'Update 21')
            ->assertJsonPath('meta.total', 21)->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/admin/notices?page=2')->assertOk()->assertJsonCount(1, 'notices')->assertJsonPath('meta.current_page', 2);
    }
}
