<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportCustomerTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_lead_cohort_conversion_and_separate_first_activation_gains(): void
    {
        foreach (range(0, 4) as $index) {
            $membership = $this->makeMembership(User::factory()->create(), ['status' => $index === 0 ? 'cancelled' : 'active']);
            $this->customerAt($index === 4 ? '2026-10-01 12:00:00' : '2026-11-01 23:59:59', $membership,
                $index === 3 ? '2026-11-03 00:00:00' : '2026-11-01 23:59:59');
            $this->membershipEvent($membership, 'activated', $index === 2 ? '2026-10-20 12:00:00' : '2026-11-01 10:00:00');
            if ($index === 0) $this->membershipEvent($membership, 'activated', '2026-11-01 11:00:00');
        }
        $this->customerAt('2026-11-02 00:00:00');
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('customers').'?from=2026-11-01&to=2026-11-01')
            ->assertOk()->assertJsonPath('summary.leads', 4)->assertJsonPath('summary.converted_leads', 2)
            ->assertJsonPath('summary.conversion_rate', 50)->assertJsonPath('summary.members_gained', 3)
            ->assertJsonPath('sources.0.leads', 4)->assertJsonPath('sources.0.conversion_rate', 50);
        $this->assertStringNotContainsString('PRIVATE INQUIRY', $response->getContent());
    }

    public function test_concern_backlog_resolution_and_feedback_use_independent_event_dates(): void
    {
        $customer = $this->customerAt('2026-10-01 12:00:00');
        foreach ([['concern', '2026-10-01 12:00:00', null], ['concern', '2026-11-01 12:00:00', '2026-11-02 00:00:00'],
            ['concern', '2026-10-02 12:00:00', '2026-11-01 12:00:00'], ['concern', '2026-11-02 00:00:00', null],
            ['feedback', '2026-11-01 23:59:59.999999', null], ['feedback', '2026-11-02 00:00:00', null]] as [$kind, $at, $resolved]) {
            DB::table('customer_interactions')->insert([
                'gym_id' => $this->gym->id, 'customer_id' => $customer->id, 'kind' => $kind, 'channel' => 'in_person',
                'body' => 'PRIVATE BODY', 'resolution' => 'PRIVATE RESOLUTION', 'recorded_by' => $this->owner->id,
                'occurred_at' => $at, 'resolved_at' => $resolved, 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('customers').'?from=2026-11-01&to=2026-11-01')
            ->assertOk()->assertJsonPath('summary.leads', 0)->assertJsonPath('summary.conversion_rate', null)
            ->assertJsonPath('summary.concerns_raised', 1)->assertJsonPath('summary.concerns_open_at_end', 2)
            ->assertJsonPath('summary.concerns_resolved', 1)->assertJsonPath('summary.feedback', 1)->assertJsonPath('meta.has_data', true);
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
    }

    public function test_empty_day_has_null_conversion_and_integer_counters(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('customers').'?from=2026-11-01&to=2026-11-01')
            ->assertOk()->assertJsonPath('meta.has_data', false)->assertJsonPath('summary.leads', 0)
            ->assertJsonPath('summary.converted_leads', 0)->assertJsonPath('summary.conversion_rate', null)->assertJsonPath('sources', []);
    }
}
