<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Room;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-21: the owner keeps the gym's training rooms and retires them. */
class RoomTest extends TestCase
{
    use RefreshDatabase, \Tests\Feature\Concerns\GivesGymRoles;

    private Gym $gym;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create();
        $this->employee = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function give(User $user, array $permissions, string $name = 'Test role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $role = $this->makeRole($permissions, $name);
        $role->users()->attach($user->id);
        $this->app->forgetScopedInstances();
    }

    public function test_owner_adds_renames_and_retires_a_room(): void
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('rooms'), ['name' => 'Main Ring'])
            ->assertCreated()
            ->assertJsonPath('room.name', 'Main Ring')
            ->assertJsonPath('room.is_active', true)
            ->json('room.id');

        $this->actingAs($this->owner)->getJson($this->url('rooms'))
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.name', 'Main Ring');

        $this->actingAs($this->owner)->putJson($this->url("rooms/{$id}"), ['name' => 'Ring A'])
            ->assertOk()
            ->assertJsonPath('room.name', 'Ring A');

        $this->actingAs($this->owner)->deleteJson($this->url("rooms/{$id}"))
            ->assertOk()
            ->assertJsonPath('room.is_active', false);

        // Retired rooms stay visible in the list but drop out of the active scope.
        $this->actingAs($this->owner)->getJson($this->url('rooms'))
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.is_active', false);

        app(CurrentGym::class)->set($this->gym);
        $this->assertSame(0, Room::active()->count());
        $this->assertSame(1, Room::count());
    }

    public function test_room_names_are_unique_inside_the_gym_but_renaming_to_itself_is_allowed(): void
    {
        $first = $this->actingAs($this->owner)->postJson($this->url('rooms'), ['name' => 'Main Ring'])
            ->assertCreated()->json('room.id');
        $second = $this->actingAs($this->owner)->postJson($this->url('rooms'), ['name' => 'Mat Room'])
            ->assertCreated()->json('room.id');

        $this->actingAs($this->owner)->postJson($this->url('rooms'), ['name' => 'Main Ring'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($this->owner)->putJson($this->url("rooms/{$second}"), ['name' => 'Main Ring'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($this->owner)->putJson($this->url("rooms/{$first}"), ['name' => 'Main Ring'])
            ->assertOk();
    }

    public function test_view_permission_can_list_rooms_but_not_change_them(): void
    {
        $this->actingAs($this->employee)->getJson($this->url('rooms'))->assertForbidden();

        $this->give($this->employee, ['gym_setup' => 'view'], 'Setup viewer');

        $this->actingAs($this->employee)->getJson($this->url('rooms'))->assertOk();
        $this->actingAs($this->employee)->postJson($this->url('rooms'), ['name' => 'Nope'])->assertForbidden();
    }

    public function test_a_room_from_another_gym_is_not_found(): void
    {
        $other = Gym::factory()->create();
        $foreign = Room::factory()->forGym($other)->create(['name' => 'Foreign Ring']);
        app(CurrentGym::class)->set($this->gym);

        $this->actingAs($this->owner)->putJson($this->url("rooms/{$foreign->id}"), ['name' => 'Stolen'])
            ->assertNotFound();
        $this->actingAs($this->owner)->deleteJson($this->url("rooms/{$foreign->id}"))
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
    }
}
