<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** R-01, R-07: the member/owner account type is chosen at sign-up and limits screens per type. */
class AccountTypeTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/register', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'birthdate' => '1995-05-10',
            ...$extra,
        ]);
    }

    public function test_register_needs_a_valid_account_type(): void
    {
        Notification::fake();

        $this->register(['account_type' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account_type');
        $this->register(['account_type' => 'staff'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account_type');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_accepts_member_and_owner(): void
    {
        Notification::fake();

        $this->register(['account_type' => 'member'])->assertCreated();
        $this->register(['email' => 'owner@example.com', 'account_type' => 'owner'])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'account_type' => 'member']);
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'account_type' => 'owner']);
    }

    public function test_a_member_cannot_apply_to_own_a_gym(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->postJson('/api/gym-applications', ['name' => 'Iron Gym', 'tier' => 1])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only Gym Owner accounts can do this.');

        $this->assertDatabaseCount('gyms', 0);
    }

    public function test_an_owner_cannot_join_a_gym_or_use_member_preferences(): void
    {
        $owner = User::factory()->owner()->create();
        $gym = Gym::factory()->approved()->create();

        $this->actingAs($owner)->postJson("/api/gyms/{$gym->id}/membership-applications", [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only Gym Member accounts can do this.');

        $this->actingAs($owner)->getJson('/api/gym-preferences')
            ->assertForbidden()
            ->assertJsonPath('message', 'Only Gym Member accounts can do this.');

        $this->actingAs($owner)->putJson('/api/gym-preferences', ['discipline' => 'boxing'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only Gym Member accounts can do this.');
    }

    public function test_an_owner_can_read_the_membership_form_but_cannot_start_an_application(): void
    {
        $owner = User::factory()->owner()->create();
        $gym = Gym::factory()->approved()->create();

        $this->actingAs($owner)->getJson('/api/membership-form')
            ->assertOk()
            ->assertJsonStructure(['health_questions', 'waivers', 'emergency_contacts_max', 'medical_file']);

        $this->actingAs($owner)->postJson("/api/gyms/{$gym->id}/membership-applications", [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only Gym Member accounts can do this.');
    }

    public function test_account_type_cannot_be_changed_through_profile_update(): void
    {
        $member = User::factory()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($member)->putJson('/api/me', [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'account_type' => 'owner',
        ])->assertOk();

        $this->actingAs($owner)->putJson('/api/me', [
            'first_name' => $owner->first_name,
            'last_name' => $owner->last_name,
            'email' => $owner->email,
            'account_type' => 'member',
        ])->assertOk();

        $this->assertSame(AccountType::Member, $member->refresh()->account_type);
        $this->assertSame(AccountType::Owner, $owner->refresh()->account_type);
    }

    public function test_an_owner_can_still_browse_gyms(): void
    {
        $owner = User::factory()->owner()->create();
        $gym = Gym::factory()->approved()->create();

        $this->actingAs($owner)->getJson('/api/gyms')
            ->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $gym->id);

        $this->actingAs($owner)->getJson("/api/gyms/{$gym->id}")
            ->assertOk()
            ->assertJsonPath('gym.id', $gym->id);
    }

    public function test_me_returns_the_account_type(): void
    {
        $member = User::factory()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($member)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.account_type', 'member');

        $this->actingAs($owner)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.account_type', 'owner');
    }

    public function test_me_reports_whether_member_records_exist(): void
    {
        $owner = User::factory()->owner()->create();
        $member = User::factory()->create();

        $this->actingAs($owner)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.has_member_records', false);

        // Members keep their links from the access type even with no records yet.
        $this->actingAs($member)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.has_member_records', false);

        $gym = Gym::factory()->approved()->create();
        $plan = MembershipPlan::factory()->forGym($gym)->create([
            'published_at' => now(),
            'total_fee' => 1500,
            'max_freeze_days' => 30,
        ]);

        DB::table('memberships')->insert([
            'gym_id' => $gym->id,
            'user_id' => $owner->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'emergency_contacts' => json_encode([]),
            'health_answers' => json_encode([]),
            'waivers' => json_encode([]),
            'signature_name' => 'Ana Reyes',
            'signed_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.has_member_records', true);
    }
}
