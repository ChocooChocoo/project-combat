<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use DecisionSupportSetup;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    private function visit(User $user, string $at, ?Gym $gym = null): void
    {
        DB::table('gym_visits')->insert([
            'gym_id' => ($gym ?? $this->gym)->id, 'user_id' => $user->id, 'checked_in_at' => $at,
            'method' => 'desk', 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    public function test_empty_member_gets_zeros(): void
    {
        $this->actingAs($this->member)->getJson('/api/me/dashboard')->assertOk()
            ->assertJsonPath('visits', [])
            ->assertJsonPath('sessions', ['attended' => 0, 'late' => 0, 'no_show' => 0, 'upcoming' => 0])
            ->assertJsonCount(6, 'monthly')
            ->assertJsonPath('monthly.5', ['period' => '2026-11', 'attended' => 0, 'paid' => '0.00'])
            ->assertJsonPath('memberships', [])
            ->assertJsonPath('balance', '0.00');
    }

    public function test_counts_only_own_rows_across_gyms(): void
    {
        $other = Gym::factory()->approved()->create();
        $other->addPerson($this->member, GymPersonType::Member);
        $stranger = User::factory()->create();

        $this->visit($this->member, '2026-11-02 08:00:00');
        $this->visit($this->member, '2026-11-02 18:00:00', $other);
        $this->visit($this->member, '2026-11-05 08:00:00');
        $this->visit($this->member, '2025-01-01 08:00:00'); // outside the 26-week window
        $this->visit($stranger, '2026-11-02 08:00:00');

        $this->markedBooking($this->occurrence('2026-11-02 10:00:00'), 'present', user: $this->member);
        $this->markedBooking($this->occurrence('2026-10-15 10:00:00'), 'late', user: $this->member);
        $this->markedBooking($this->occurrence('2026-11-03 10:00:00'), 'no_show', user: $this->member);
        $this->markedBooking($this->occurrence('2026-11-09 10:00:00'), null, user: $this->member);
        $this->markedBooking($this->occurrence('2026-11-02 11:00:00'), 'present', user: $stranger);

        $this->income('membership', '500.00', '2026-11-02 10:00:00');
        $this->income('membership', '9999.00', '2026-11-02 10:00:00', ['user_id' => $stranger->id]);
        $this->makeMembership($this->member, ['starts_on' => '2026-11-01', 'ends_on' => '2026-11-17']);
        $this->app->forgetScopedInstances(); // a fresh request has no gym set

        $response = $this->actingAs($this->member)->getJson('/api/me/dashboard')->assertOk()
            ->assertJsonPath('sessions', ['attended' => 2, 'late' => 1, 'no_show' => 1, 'upcoming' => 1])
            ->assertJsonPath('monthly.4.attended', 1)
            ->assertJsonPath('monthly.5', ['period' => '2026-11', 'attended' => 1, 'paid' => '500.00'])
            ->assertJsonPath('memberships.0.days_left', 10);

        $visits = collect($response->json('visits'))->pluck('count', 'date')->all();
        $this->assertSame(['2026-11-02' => 2, '2026-11-05' => 1], $visits);
    }
}
