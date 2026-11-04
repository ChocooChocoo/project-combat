<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymHoliday;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** R-27: national holidays plus the gym's own holidays. */
class HolidayTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
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

    public function test_viewing_holidays_never_writes_and_includes_national_days(): void
    {
        $response = $this->actingAs($this->employee)->getJson($this->url('holidays?year=2026'))->assertOk();

        $holidays = collect($response->json('holidays'));
        $christmas = $holidays->firstWhere('date', '2026-12-25');

        $this->assertNotNull($christmas);
        $this->assertSame('Christmas Day', $christmas['name']);
        $this->assertTrue($christmas['national']);
        $this->assertDatabaseCount('gym_holidays', 0);
    }

    public function test_owner_adds_and_removes_a_gym_holiday(): void
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('holidays'), [
            'date' => '2026-12-24',
            'name' => 'Gym party',
        ])
            ->assertCreated()
            ->assertJsonPath('holiday.date', '2026-12-24')
            ->assertJsonPath('holiday.name', 'Gym party')
            ->assertJsonPath('holiday.national', false)
            ->json('holiday.id');

        $this->assertSame(1, DB::table('gym_holidays')
            ->where('id', $id)
            ->where('gym_id', $this->gym->id)
            ->whereDate('date', '2026-12-24')
            ->count());

        $this->actingAs($this->owner)->getJson($this->url('holidays?year=2026'))
            ->assertOk()
            ->assertJsonFragment(['date' => '2026-12-24', 'name' => 'Gym party', 'national' => false]);

        $this->actingAs($this->owner)->deleteJson($this->url("holidays/{$id}"))->assertOk();
        $this->assertDatabaseCount('gym_holidays', 0);
    }

    public function test_a_gym_can_only_have_one_holiday_per_date(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('holidays'), [
            'date' => '2026-12-24',
            'name' => 'Gym party',
        ])->assertCreated();

        $this->actingAs($this->owner)->postJson($this->url('holidays'), [
            'date' => '2026-12-24',
            'name' => 'Another party',
        ])->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_an_employee_cannot_add_a_holiday(): void
    {
        $this->actingAs($this->employee)->postJson($this->url('holidays'), [
            'date' => '2026-12-24',
            'name' => 'Gym party',
        ])->assertForbidden();
    }

    public function test_another_gyms_holiday_is_not_found(): void
    {
        $otherGym = Gym::factory()->create();
        $holiday = GymHoliday::factory()->forGym($otherGym)->create();

        $this->actingAs($this->owner)->deleteJson($this->url("holidays/{$holiday->id}"))->assertNotFound();
    }
}
