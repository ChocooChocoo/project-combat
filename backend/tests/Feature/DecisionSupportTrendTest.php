<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportTrendTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_year_spanning_twelve_months_keep_old_opening_history_and_clip_final_day(): void
    {
        $old = $this->makeMembership($this->member);
        $this->membershipEvent($old, 'activated', '2025-01-01 10:00:00');
        $this->membershipEvent($old, 'frozen', '2025-12-01 10:00:00');
        $this->membershipEvent($old, 'expired', '2026-01-10 10:00:00');
        $new = $this->makeMembership($this->member);
        $this->membershipEvent($new, 'activated', '2025-12-03 10:00:00');
        $this->membershipEvent($new, 'activated', '2026-01-05 10:00:00');
        $this->membershipEvent($new, 'renewed', '2025-12-15 10:00:00');
        $this->membershipEvent($new, 'cancelled', '2026-02-01 10:00:00');
        $this->markedBooking($this->occurrence('2025-02-01 10:00:00'), 'present');
        $session = $this->occurrence('2025-12-31 23:59:59', ['status' => 'cancelled']);
        foreach (['present', 'present', 'late', 'absent', 'no_show'] as $marker) $this->markedBooking($session, $marker);
        $this->markedBooking($this->occurrence('2026-01-01 00:00:00'), 'present');
        $this->markedBooking($this->occurrence('2026-02-16 00:00:00'), 'present');
        $this->income('membership', '100.00', '2025-12-01 10:00:00');
        $this->income('membership', '200.00', '2026-01-01 10:00:00');
        $this->income('membership', '-50.00', '2026-01-02 10:00:00');
        $this->income('membership', '999.00', '2026-02-16 00:00:00');
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('trends').'?from=2026-02-14&to=2026-02-15')->assertOk()
            ->assertJsonPath('window.from', '2025-03-01')->assertJsonPath('window.to', '2026-02-15')
            ->assertJsonPath('window.months', 12)->assertJsonPath('window.partial_final_month', true)->assertJsonCount(12, 'buckets')
            ->assertJsonPath('first_records.attendance', '2025-02-01')->assertJsonPath('first_records.memberships', '2025-01-01')
            ->assertJsonPath('first_records.revenue', '2025-12-01')->assertJsonPath('totals.attended', 4)->assertJsonPath('totals.marked', 6)
            ->assertJsonPath('totals.attendance_rate', 66.67)->assertJsonPath('totals.new', 1)->assertJsonPath('totals.renewed', 1)
            ->assertJsonPath('totals.expired', 1)->assertJsonPath('totals.cancelled', 1)->assertJsonPath('totals.revenue', '250.00')
            ->assertJsonPath('closing_active', 0);
        $buckets = collect($response->json('buckets'))->keyBy('period');
        $this->assertSame(1, $buckets['2025-03']['active_at_end']);
        $this->assertSame(2, $buckets['2025-12']['active_at_end']);
        $this->assertSame(1, $buckets['2026-01']['active_at_end']);
        $this->assertSame(0, $buckets['2026-02']['active_at_end']);
        $this->assertSame(3, $buckets['2025-12']['attended']);
        $this->assertSame(5, $buckets['2025-12']['marked']);
        $this->assertSame(60, $buckets['2025-12']['attendance_rate']);
        $this->assertSame('0.00', $buckets['2025-04']['revenue']);
        $this->assertSame(['2025-03', '2025-04', '2025-05', '2025-06', '2025-07', '2025-08', '2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02'], array_keys($buckets->all()));
    }

    public function test_empty_future_only_and_unknown_markers_do_not_establish_source_history(): void
    {
        $this->markedBooking($this->occurrence('2026-01-01 10:00:00'), 'unknown');
        $this->markedBooking($this->occurrence('2026-11-08 00:00:00'), 'present');
        $this->income('other', '100.00', '2026-11-08 00:00:00');
        $membership = $this->makeMembership($this->member, ['status' => 'awaiting_payment']);
        $this->membershipEvent($membership, 'activated', '2026-11-08 00:00:00');
        $this->actingAs($this->owner)->getJson($this->decisionUrl('trends').'?from=2027-01-01&to=2027-01-01')->assertOk()
            ->assertJsonPath('window.to', '2026-11-07')->assertJsonPath('first_records.attendance', null)
            ->assertJsonPath('first_records.memberships', null)->assertJsonPath('first_records.revenue', null)
            ->assertJsonPath('totals.attendance_rate', null)->assertJsonPath('meta.has_data', false);
    }
}
