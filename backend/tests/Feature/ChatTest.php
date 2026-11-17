<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\ChatMessage;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\SystemRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** R-145: chat inside one gym. Members reach the staff and the owner, never each other. */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Gym $otherGym;

    private User $owner;

    private User $coach;

    private User $staff;

    private User $member;

    private User $otherMember;

    private User $outsider;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->approved()->create(['name' => 'Gym A']);
        $this->otherGym = Gym::factory()->approved()->create(['name' => 'Gym B']);

        $this->owner = User::factory()->owner()->create(['first_name' => 'Olivia', 'last_name' => 'Reyes']);
        $this->gym->addPerson($this->owner, GymPersonType::Owner);

        $this->coach = User::factory()->create(['first_name' => 'Carlo', 'last_name' => 'Diaz']);
        $this->gym->addPerson($this->coach, GymPersonType::Employee);
        EmployeeProfile::factory()->forGym($this->gym)->create(['user_id' => $this->coach->id, 'coach_rank' => 'Black belt']);

        $this->staff = User::factory()->create(['first_name' => 'Sam', 'last_name' => 'Cruz']);
        $this->gym->addPerson($this->staff, GymPersonType::Employee);
        EmployeeProfile::factory()->forGym($this->gym)->create(['user_id' => $this->staff->id, 'job_title' => 'Front desk']);

        $this->member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Santos']);
        $this->gym->addPerson($this->member, GymPersonType::Member);

        $this->otherMember = User::factory()->create(['first_name' => 'Noel', 'last_name' => 'Tan']);
        $this->gym->addPerson($this->otherMember, GymPersonType::Member);

        $this->outsider = User::factory()->create(['first_name' => 'Oscar', 'last_name' => 'Lim']);
        $this->otherGym->addPerson($this->outsider, GymPersonType::Member);
        $this->stranger = User::factory()->create(['first_name' => 'Stella', 'last_name' => 'Uy']);

        SystemRoles::seed($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/chat/{$path}";
    }

    private function start(User $me, User $other): TestResponse
    {
        return $this->actingAs($me)->postJson($this->url('conversations'), ['with_user_id' => $other->id]);
    }

    /** The thread id these two share, starting it when they have none yet. */
    private function thread(User $me, User $other): int
    {
        return $this->start($me, $other)->assertCreated()->json('conversation.id');
    }

    private function say(User $sender, int $conversation, string $body): TestResponse
    {
        return $this->actingAs($sender)->postJson($this->url("conversations/{$conversation}/messages"), ['body' => $body]);
    }

    public function test_a_member_reaches_a_coach_and_the_owner_and_one_thread_is_kept(): void
    {
        $coachThread = $this->start($this->member, $this->coach)->assertCreated()->json('conversation.id');
        $this->assertSame($coachThread, $this->start($this->member, $this->coach)->assertCreated()->json('conversation.id'));
        $this->assertSame($coachThread, $this->start($this->coach, $this->member)->assertCreated()->json('conversation.id'));

        $ownerThread = $this->start($this->member, $this->owner)->assertCreated()->json('conversation.id');
        $this->assertSame($ownerThread, $this->start($this->member, $this->owner)->assertCreated()->json('conversation.id'));
        $this->assertNotSame($coachThread, $ownerThread);

        // The lower id is always user_one, so the pair is unique whichever side starts.
        $ids = [$this->member->id, $this->coach->id];
        $this->assertDatabaseHas('chat_conversations', [
            'id' => $coachThread, 'user_one_id' => min($ids), 'user_two_id' => max($ids),
        ]);
        $this->assertDatabaseCount('chat_conversations', 2);
    }

    public function test_a_member_cannot_message_another_member(): void
    {
        $this->start($this->member, $this->otherMember)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Members cannot message other members. Message the gym staff instead.');

        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_a_person_cannot_chat_with_themselves(): void
    {
        $this->start($this->member, $this->member)
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot start a chat with this person.');

        $this->start($this->owner, $this->owner)->assertStatus(422);
        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_people_outside_the_gym_are_refused(): void
    {
        foreach ([$this->outsider, $this->stranger] as $person) {
            $this->actingAs($person)->getJson($this->url('partners'))->assertNotFound();
            $this->actingAs($person)->getJson($this->url('conversations'))->assertNotFound();
            $this->actingAs($person)->getJson($this->url('unread-count'))->assertNotFound();
            $this->actingAs($person)->postJson($this->url('conversations'), ['with_user_id' => $this->member->id])->assertNotFound();
        }

        // Someone who belongs only to another gym cannot be reached from this one.
        $this->start($this->member, $this->outsider)
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot start a chat with this person.');

        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_the_partner_sees_one_unread_message_until_the_thread_is_opened(): void
    {
        $conversation = $this->thread($this->member, $this->coach);

        $this->say($this->member, $conversation, '  Can I book a private session?  ')
            ->assertCreated()
            ->assertJsonPath('message.body', 'Can I book a private session?')
            ->assertJsonPath('message.from_me', true)
            ->assertJsonPath('message.sender_name', 'Mia Santos')
            ->assertJsonPath('conversation.unread_count', 0);

        $this->actingAs($this->coach)->getJson($this->url('conversations'))
            ->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.id', $conversation)
            ->assertJsonPath('conversations.0.partner.id', $this->member->id)
            ->assertJsonPath('conversations.0.partner.name', 'Mia Santos')
            ->assertJsonPath('conversations.0.partner.role_label', 'Member')
            ->assertJsonPath('conversations.0.last_message.body', 'Can I book a private session?')
            ->assertJsonPath('conversations.0.last_message.from_me', false)
            ->assertJsonPath('conversations.0.unread_count', 1);

        $this->actingAs($this->coach)->getJson($this->url('unread-count'))->assertOk()->assertJsonPath('unread_count', 1);

        $this->actingAs($this->coach)->postJson($this->url("conversations/{$conversation}/read"))
            ->assertOk()->assertJsonPath('unread_count', 0);

        $this->actingAs($this->coach)->getJson($this->url('conversations'))
            ->assertJsonPath('conversations.0.unread_count', 0);
        $this->actingAs($this->coach)->getJson($this->url('unread-count'))->assertJsonPath('unread_count', 0);

        // The member's own message is never counted as unread for them.
        $this->actingAs($this->member)->getJson($this->url('unread-count'))->assertJsonPath('unread_count', 0);

        $this->actingAs($this->member)
            ->getJson($this->url("conversations/{$conversation}?page=1"))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.from_me', true)
            ->assertJsonPath('messages.0.sender_name', 'Mia Santos')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_third_person_cannot_read_send_to_or_mark_a_thread_read(): void
    {
        $conversation = $this->thread($this->member, $this->coach);
        $this->say($this->member, $conversation, 'Hello coach.')->assertCreated();

        foreach ([$this->staff, $this->otherMember, $this->owner] as $onlooker) {
            $this->actingAs($onlooker)->getJson($this->url("conversations/{$conversation}"))->assertNotFound();
            $this->say($onlooker, $conversation, 'Let me in.')->assertNotFound();
            $this->actingAs($onlooker)->postJson($this->url("conversations/{$conversation}/read"))->assertNotFound();
        }

        $this->assertSame(1, ChatMessage::count());
    }

    public function test_a_message_body_must_be_real_text_up_to_two_thousand_characters(): void
    {
        $conversation = $this->thread($this->member, $this->coach);

        $this->say($this->member, $conversation, '')->assertStatus(422)->assertJsonValidationErrors('body');
        $this->say($this->member, $conversation, '    ')->assertStatus(422)->assertJsonValidationErrors('body');
        $this->say($this->member, $conversation, str_repeat('a', 2001))->assertStatus(422)->assertJsonValidationErrors('body');
        $this->say($this->member, $conversation, str_repeat('a', 2000))->assertCreated()->assertJsonPath('message.body', str_repeat('a', 2000));

        $this->assertSame(1, ChatMessage::count());
    }

    public function test_the_partner_picker_offers_the_people_each_role_may_reach(): void
    {
        $memberPartners = $this->actingAs($this->member)->getJson($this->url('partners'))
            ->assertOk()->assertJsonCount(3, 'partners')->json('partners');
        $this->assertSame(['Carlo Diaz', 'Olivia Reyes', 'Sam Cruz'], collect($memberPartners)->pluck('name')->sort()->values()->all());
        $this->assertSame(['Coach', 'Owner', 'Staff'], collect($memberPartners)->pluck('role_label')->sort()->values()->all());
        $this->assertSame([null, null, null], collect($memberPartners)->pluck('conversation_id')->all());
        $this->assertNotContains('Noel Tan', collect($memberPartners)->pluck('name'));

        // An existing thread shows up in the picker instead of a second one being made.
        $conversation = $this->thread($this->member, $this->coach);
        $withThread = $this->actingAs($this->member)->getJson($this->url('partners'))->json('partners');
        $this->assertSame($conversation, collect($withThread)->firstWhere('id', $this->coach->id)['conversation_id']);
        $this->assertNull(collect($withThread)->firstWhere('id', $this->owner->id)['conversation_id']);

        $ownerPartners = $this->actingAs($this->owner)->getJson($this->url('partners'))
            ->assertOk()->assertJsonCount(4, 'partners')->json('partners');
        $this->assertSame(['Carlo Diaz', 'Mia Santos', 'Noel Tan', 'Sam Cruz'], collect($ownerPartners)->pluck('name')->sort()->values()->all());

        $coachPartners = $this->actingAs($this->coach)->getJson($this->url('partners'))
            ->assertOk()->assertJsonCount(4, 'partners')->json('partners');
        $this->assertContains('Mia Santos', collect($coachPartners)->pluck('name'));
        $this->assertContains('Noel Tan', collect($coachPartners)->pluck('name'));
    }

    public function test_threads_from_another_gym_never_show_up(): void
    {
        $this->otherGym->addPerson($this->member, GymPersonType::Member);
        $otherOwner = User::factory()->owner()->create(['first_name' => 'Other', 'last_name' => 'Owner']);
        $this->otherGym->addPerson($otherOwner, GymPersonType::Owner);

        app(CurrentGym::class)->set($this->otherGym);
        $otherThread = $this->actingAs($otherOwner)
            ->postJson("/api/gyms/{$this->otherGym->id}/chat/conversations", ['with_user_id' => $this->member->id])
            ->assertCreated()->json('conversation.id');
        $this->actingAs($otherOwner)
            ->postJson("/api/gyms/{$this->otherGym->id}/chat/conversations/{$otherThread}/messages", ['body' => 'Gym B only.'])
            ->assertCreated();

        $mine = $this->actingAs($this->member)->getJson($this->url('conversations'))
            ->assertOk()->assertJsonCount(0, 'conversations')->json('conversations');
        $this->assertSame([], $mine);
        $this->actingAs($this->member)->getJson($this->url('unread-count'))->assertJsonPath('unread_count', 0);
        $this->actingAs($this->member)->getJson($this->url("conversations/{$otherThread}"))->assertNotFound();

        // Rewriting a thread of this gym is refused even when the person is in both gyms.
        $conversation = $this->thread($this->member, $this->coach);
        $this->say($this->member, $conversation, 'Gym A only.')->assertCreated();
        $this->actingAs($this->member)->getJson($this->url('conversations'))->assertJsonCount(1, 'conversations');
        $this->actingAs($otherOwner)->getJson("/api/gyms/{$this->otherGym->id}/chat/conversations")->assertJsonCount(1, 'conversations');
    }
}
