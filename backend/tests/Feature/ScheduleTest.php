<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-24: weekly schedules and days off. */
class ScheduleTest extends TestCase
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

    /** @return array<int, array{weekday: string, start_time: string|null, end_time: string|null}> */
    private function week(string $start = '08:00', string $end = '17:00'): array
    {
        return collect(config('hr.weekdays'))
            ->map(fn (string $label, string $weekday) => [
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
            ])
            ->values()
            ->all();
    }

    public function test_owner_saves_the_week_and_a_day_off(): void
    {
        $schedule = $this->week();
        $schedule[5]['start_time'] = null;
        $schedule[5]['end_time'] = null;

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/schedule"), [
            'schedule' => $schedule,
        ])
            ->assertOk()
            ->assertJsonCount(7, 'schedule')
            ->assertJsonPath('schedule.5.weekday', 'sat')
            ->assertJsonPath('schedule.5.start_time', null)
            ->assertJsonPath('schedule.5.is_day_off', true)
            ->assertJsonPath('schedule.0.start_time', '08:00')
            ->assertJsonPath('schedule.0.is_day_off', false);

        $this->assertDatabaseCount('work_schedules', 7);
        $this->assertDatabaseHas('work_schedules', [
            'gym_id' => $this->gym->id,
            'user_id' => $this->employee->id,
            'weekday' => 'sat',
            'start_time' => null,
        ]);
    }

    public function test_an_employee_can_see_their_own_schedule_but_cannot_change_it(): void
    {
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/schedule"), [
            'schedule' => $this->week(),
        ])->assertOk();

        $this->actingAs($this->employee)->getJson($this->url("staff/{$this->employee->id}/schedule"))
            ->assertOk()
            ->assertJsonCount(7, 'schedule')
            ->assertJsonPath('schedule.0.end_time', '17:00');

        $this->actingAs($this->employee)->putJson($this->url("staff/{$this->employee->id}/schedule"), [
            'schedule' => $this->week(),
        ])->assertForbidden();
    }

    public function test_the_schedule_needs_each_weekday_once_and_valid_times(): void
    {
        $duplicate = $this->week();
        $duplicate[6]['weekday'] = 'mon';

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/schedule"), [
            'schedule' => $duplicate,
        ])->assertStatus(422)->assertJsonValidationErrors('schedule');

        $backwards = $this->week();
        $backwards[0]['end_time'] = '07:00';

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/schedule"), [
            'schedule' => $backwards,
        ])->assertStatus(422)->assertJsonValidationErrors('schedule.0.end_time');

        $missing = $this->week();
        $missing[0]['start_time'] = null;

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/schedule"), [
            'schedule' => $missing,
        ])->assertStatus(422)->assertJsonValidationErrors('schedule.0.end_time');
    }
}
