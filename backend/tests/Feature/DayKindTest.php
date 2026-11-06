<?php

namespace Tests\Feature;

use App\Enums\DayKind;
use App\Models\Gym;
use App\Models\GymHoliday;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;
use App\Support\WorkingDays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** R-27, R-56, R-59: one day classification for attendance and payroll. */
class DayKindTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->employee = User::factory()->create();
        app(CurrentGym::class)->set($this->gym);
    }

    private function kind(string $date): DayKind
    {
        return app(WorkingDays::class)->kind($this->gym, $this->employee, Carbon::parse($date));
    }

    public function test_national_holidays_carry_their_kind(): void
    {
        $this->assertSame(DayKind::HolidayRegular, $this->kind('2026-12-25'));
        $this->assertSame(DayKind::HolidaySpecial, $this->kind('2026-11-01'));
    }

    public function test_a_gym_holiday_is_a_special_holiday_even_on_a_working_day(): void
    {
        GymHoliday::create(['date' => '2026-11-10', 'name' => 'Founding day']);

        $this->assertSame(DayKind::HolidaySpecial, $this->kind('2026-11-10'));
    }

    public function test_a_timed_schedule_beats_the_gym_week(): void
    {
        WorkSchedule::create([
            'user_id' => $this->employee->id,
            'weekday' => 'mon',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertSame(DayKind::Working, $this->kind('2026-11-09'));
        $this->assertSame(DayKind::Rest, $this->kind('2026-11-10'));
    }

    public function test_the_gym_week_applies_without_a_timed_schedule(): void
    {
        $this->assertSame(DayKind::Working, $this->kind('2026-11-06'));
        $this->assertSame(DayKind::Rest, $this->kind('2026-11-07'));
    }

    public function test_an_untimed_schedule_falls_back_to_the_gym_week(): void
    {
        WorkSchedule::create([
            'user_id' => $this->employee->id,
            'weekday' => 'mon',
            'start_time' => null,
            'end_time' => null,
        ]);

        $this->assertSame(DayKind::Working, $this->kind('2026-11-06'));
        $this->assertSame(DayKind::Rest, $this->kind('2026-11-07'));
    }
}
