<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** R-10: each person's notification inbox. */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notice(User $user, bool $read = false): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'test',
            'data' => ['title' => 'Hello'],
            'read_at' => $read ? now() : null,
        ]);

        return $id;
    }

    public function test_inbox_shows_only_own_notices_with_unread_count(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->notice($me);
        $this->notice($me, read: true);
        $theirs = $this->notice($other);

        $this->actingAs($me)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'notifications.data');

        $this->actingAs($me)->postJson("/api/notifications/{$theirs}/read")->assertNotFound();
    }

    public function test_unread_count_counts_only_own_unread_notices_and_updates_after_marking_read(): void
    {
        $me = User::factory()->create();
        $first = $this->notice($me);
        $this->notice($me);
        $this->notice($me, read: true);
        $this->notice(User::factory()->create());

        $this->actingAs($me)->getJson('/api/notifications/unread-count')
            ->assertOk()->assertExactJson(['unread_count' => 2]);
        $this->postJson("/api/notifications/{$first}/read")->assertOk();
        $this->getJson('/api/notifications/unread-count')
            ->assertOk()->assertExactJson(['unread_count' => 1]);
    }

    public function test_mark_read_mark_all_and_clear_read(): void
    {
        $me = User::factory()->create();
        $first = $this->notice($me);
        $this->notice($me);

        $this->actingAs($me)->postJson("/api/notifications/{$first}/read")->assertOk();
        $this->assertSame(1, $me->unreadNotifications()->count());

        $this->actingAs($me)->postJson('/api/notifications/read-all')->assertOk();
        $this->assertSame(0, $me->unreadNotifications()->count());

        $this->actingAs($me)->deleteJson('/api/notifications/read')->assertOk();
        $this->assertSame(0, $me->notifications()->count());
    }
}
