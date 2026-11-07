<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportProblemTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    private array $athletes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
        $this->travelTo(now()->setDate(2026, 12, 1));
    }

    private function problemOccurrence(string $name, string $at, int $present, int $marked, array $attributes = []): int
    {
        $session = $this->occurrence($at, ['name' => $name, 'capacity' => 10, ...$attributes]);
        while (count($this->athletes) < $marked) $this->athletes[] = User::factory()->create()->id;
        $rows = [];
        for ($i = 0; $i < $marked; $i++) $rows[] = ['gym_id' => $this->gym->id, 'training_session_id' => $session->id,
            'user_id' => $this->athletes[$i], 'status' => 'booked', 'attendance' => $i < $present ? 'present' : 'absent'];
        foreach (array_chunk($rows, 500) as $chunk) DB::table('session_bookings')->insert($chunk);

        return $session->id;
    }

    private function problems(): array
    {
        return $this->actingAs($this->owner)->getJson($this->decisionUrl('problems').'?from=2026-11-01&to=2026-11-30')->assertOk()->json();
    }

    public function test_falling_attendance_pools_the_latest_eight_completed_occurrences_with_evidence(): void
    {
        $ids = [];
        for ($day = 1; $day <= 8; $day++) $ids[] = $this->problemOccurrence('Falling', sprintf('2026-11-%02d 10:00:00', $day), $day <= 4 ? 8 : 5, 10);
        $this->problemOccurrence('Falling', '2026-11-09 10:00:00', 10, 10, ['status' => 'cancelled']);
        $this->problemOccurrence('Falling', '2026-12-01 10:00:00', 10, 10);
        $this->problemOccurrence('Falling', '2026-10-31 10:00:00', 0, 10);
        $this->problemOccurrence('Falling', '2026-11-30 23:00:00', 10, 10, ['ends_at' => '2026-12-01 01:00:00']);
        $data = $this->problems();
        $flag = collect($data['items'])->firstWhere('code', 'falling_attendance');
        $this->assertSame($ids, $flag['evidence']['session_ids']);
        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-04', 'attended' => 32, 'marked' => 40, 'rate' => 80], $flag['evidence']['previous']);
        $this->assertSame(['from' => '2026-11-05', 'to' => '2026-11-08', 'attended' => 20, 'marked' => 40, 'rate' => 50], $flag['evidence']['current']);
        $this->assertSame(30, $flag['evidence']['drop_points']);
        $this->assertSame(1, $data['summary']['falling_attendance']);
    }

    public function test_falling_boundaries_and_unmarked_occurrences_do_not_select_older_markers(): void
    {
        foreach (['Exact drop' => [7, 5, 8], 'Current sixty' => [8, 6, 8], 'Small drop' => [6, 5, 8], 'Seven only' => [8, 5, 7], 'Zero denominator' => [8, 0, 8]] as $name => [$before, $after, $count]) {
            for ($day = 1; $day <= $count; $day++) $this->problemOccurrence($name, sprintf('2026-11-%02d 11:00:00', $day), $day <= 4 ? $before : $after, $name === 'Zero denominator' && $day > 4 ? 0 : 10);
        }
        for ($day = 1; $day <= 9; $day++) $this->problemOccurrence('Unmarked latest', sprintf('2026-11-%02d 12:00:00', $day), $day <= 5 ? 8 : 0, $day <= 5 ? 10 : 0);
        $falling = collect($this->problems()['items'])->where('code', 'falling_attendance');
        $this->assertSame(['Exact drop'], $falling->pluck('name')->values()->all());
        $this->assertSame(20, $falling->first()['evidence']['drop_points']);
    }

    public function test_low_use_is_an_unweighted_mean_with_requested_places_and_exact_boundaries(): void
    {
        $ids = [];
        foreach (['2026-11-01', '2026-11-08', '2026-11-15'] as $index => $day) {
            $session = $this->occurrence($day.' 00:00:00', ['capacity' => [100, 5, 5][$index]]);
            $ids[] = $session->id;
            for ($i = 0; $i < $index; $i++) $this->markedBooking($session, null, 'requested');
            $this->problemOccurrence('Exact quarter', $day.' 13:00:00', 1, 1, ['capacity' => 4]);
        }
        foreach (['2026-11-02', '2026-11-09'] as $day) $this->problemOccurrence('Only two', $day.' 15:00:00', 0, 0);
        $this->problemOccurrence('Invalid', '2026-11-22 00:00:00', 0, 0, ['capacity' => 0]);
        $this->problemOccurrence('Cancelled', '2026-11-29 00:00:00', 0, 0, ['status' => 'cancelled']);
        $flags = collect($this->problems()['items'])->where('code', 'low_use_slot')->values();
        $this->assertCount(1, $flags);
        $this->assertSame(['weekday' => 0, 'hour' => 0, 'sessions' => 3, 'held_seats' => 3, 'capacity' => 110, 'average_fill' => 20, 'session_ids' => $ids], $flags[0]['evidence']);
    }

    public function test_no_show_uses_known_markers_only_and_discloses_unmarked_bookings(): void
    {
        $session = $this->occurrence('2026-11-30 23:00:00', ['ends_at' => '2026-11-30 23:59:59']);
        foreach (['no_show', 'no_show', 'no_show', 'present', 'late', 'absent', 'present', 'present', 'absent', 'present', null, 'unknown'] as $marker) $this->markedBooking($session, $marker);
        $this->markedBooking($session, null, 'requested');
        $small = $this->occurrence('2026-11-02 11:00:00', ['name' => 'Small']);
        foreach (['no_show', 'no_show', 'present', 'absent'] as $marker) $this->markedBooking($small, $marker);
        $below = $this->problemOccurrence('Below thirty', '2026-11-02 12:00:00', 5, 10);
        $ids = DB::table('session_bookings')->where('training_session_id', $below)->where('attendance', 'absent')->limit(2)->pluck('id');
        DB::table('session_bookings')->whereIn('id', $ids)->update(['attendance' => 'no_show']);
        $data = $this->problems();
        $flags = collect($data['items'])->where('code', 'high_no_show')->values();
        $this->assertCount(1, $flags);
        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-30', 'marked' => 10, 'no_show' => 3, 'awaiting_attendance' => 1, 'rate' => 30], $flags[0]['evidence']);
        $this->assertSame(config('decision_support'), $data['thresholds']);
        config(['decision_support.high_no_show.rate_at_least' => 31, 'decision_support.low_use_slot.minimum_sessions' => 99]);
        $changed = $this->problems();
        $this->assertSame(0, $changed['summary']['high_no_show']);
        $this->assertSame(31, $changed['thresholds']['high_no_show']['rate_at_least']);
    }

    public function test_empty_one_day_and_changed_thresholds_are_honest(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('problems').'?from=2026-11-01&to=2026-11-01')
            ->assertOk()->assertJsonPath('meta.has_data', false)->assertJsonPath('items', [])->assertJsonPath('summary.total', 0);
        $this->problemOccurrence('One day', '2026-11-01 23:59:59', 0, 0, ['ends_at' => '2026-11-02 00:59:59']);
        $this->problemOccurrence('Next day', '2026-11-02 00:00:00', 0, 0);
        config(['decision_support.low_use_slot.minimum_sessions' => 1, 'decision_support.falling_attendance.window_sessions' => 1]);
        $this->actingAs($this->owner)->getJson($this->decisionUrl('problems').'?from=2026-11-01&to=2026-11-01')
            ->assertOk()->assertJsonPath('meta.has_data', true)->assertJsonPath('summary.total', 1)
            ->assertJsonPath('items.0.evidence.sessions', 1)->assertJsonPath('thresholds.low_use_slot.minimum_sessions', 1);
    }

    public function test_a_19_point_99_drop_is_not_rounded_up_and_config_controls_falling_detection(): void
    {
        $template = $this->member->getAttributes();
        unset($template['id']);
        for ($chunk = 0; $chunk < 20; $chunk++) {
            $users = [];
            for ($i = 0; $i < 500; $i++) $users[] = [...$template, 'email' => 'problem-'.($chunk * 500 + $i).'@example.test'];
            DB::table('users')->insert($users);
        }
        $this->athletes = DB::table('users')->where('email', 'like', 'problem-%')->pluck('id')->all();
        $ids = [];
        for ($day = 1; $day <= 8; $day++) {
            $ids[] = $this->problemOccurrence('Below raw threshold', sprintf('2026-11-%02d 10:00:00', $day),
                $day === 1 ? 6999 : ($day === 5 ? 1 : 0), $day === 1 ? 10000 : ($day === 5 ? 2 : 0));
        }
        $this->assertSame(0, $this->problems()['summary']['falling_attendance']);
        config(['decision_support.falling_attendance.drop_points' => 19.98]);
        $data = $this->problems();
        $this->assertSame(1, $data['summary']['falling_attendance']);
        $this->assertSame(19.98, $data['thresholds']['falling_attendance']['drop_points']);
        $flag = collect($data['items'])->firstWhere('code', 'falling_attendance');
        $this->assertEquals(19.99, $flag['evidence']['drop_points']);
        $this->assertSame($ids, $flag['evidence']['session_ids']);
    }

    public function test_exact_twenty_point_drop_is_inclusive_even_with_repeating_fraction_rates(): void
    {
        for ($day = 1; $day <= 8; $day++) $this->problemOccurrence('Fractional exact drop', sprintf('2026-11-%02d 10:00:00', $day), $day <= 4 ? 7 : 4, 15);
        $data = $this->problems();
        $this->assertSame(1, $data['summary']['falling_attendance']);
        $flag = collect($data['items'])->firstWhere('code', 'falling_attendance');
        $this->assertSame(20, $flag['evidence']['drop_points']);
        $this->assertSame(46.67, $flag['evidence']['previous']['rate']);
        $this->assertSame(26.67, $flag['evidence']['current']['rate']);
    }
}
