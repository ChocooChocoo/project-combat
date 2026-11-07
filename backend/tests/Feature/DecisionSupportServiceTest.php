<?php

namespace Tests\Feature;

use App\Models\GymService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportServiceTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_current_held_seats_and_pooled_fill_preserve_zero_services_and_private_offers(): void
    {
        $service = GymService::factory()->forGym($this->gym)->create(['name' => 'Boxing']);
        $zero = GymService::factory()->forGym($this->gym)->create(['name' => 'Unused']);
        $first = $this->occurrence('2026-11-02 10:00:00', ['gym_service_id' => $service->id, 'capacity' => 4]);
        $second = $this->occurrence('2026-11-03 10:00:00', ['gym_service_id' => $service->id, 'capacity' => 12, 'room_id' => $first->room_id]);
        foreach ([$first, $second] as $session) {
            $this->markedBooking($session, 'present');
            $this->markedBooking($session, null, 'requested');
            foreach (['waitlisted', 'rejected', 'cancelled'] as $status) $this->markedBooking($session, null, $status);
        }
        $this->markedBooking($second, null);
        $this->occurrence('2026-11-03 12:00:00', ['status' => 'cancelled', 'capacity' => 100]);
        $private = $this->occurrence('2026-11-03 13:00:00', ['type' => 'private_coaching', 'capacity' => 1]);
        $this->markedBooking($private, null, 'requested');
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('services').'?from=2026-11-02&to=2026-11-03')->assertOk()
            ->assertJsonPath('summary.held_seats', 5)->assertJsonPath('summary.capacity', 16)
            ->assertJsonPath('summary.fill_rate', 31.25)->assertJsonPath('summary.confirmed_bookings', 3)
            ->assertJsonPath('summary.sessions', 2)->assertJsonPath('summary.scheduled_minutes', 120)
            ->assertJsonPath('summary.delivered_sessions', 2)->assertJsonPath('summary.delivered_minutes', 120)
            ->assertJsonPath('private_coaching.offered_sessions', 1)->assertJsonPath('private_coaching.booked_sessions', 1)
            ->assertJsonPath('private_coaching.booking_rate', 100)
            ->assertJsonPath('least_booked.0.service_id', $zero->id)
            ->assertJsonPath('most_booked.0.service_id', $service->id);
        $this->assertCount(3, $response->json('services'));
        $this->assertSame(0, collect($response->json('services'))->firstWhere('service_id', $zero->id)['held_seats']);
        $this->assertSame(120, collect($response->json('rooms'))->firstWhere('room_id', $first->room_id)['delivered_minutes']);
    }

    public function test_empty_zero_demand_ties_and_invalid_capacity_are_honest(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('services').'?from=2026-11-02&to=2026-11-02')
            ->assertOk()->assertJsonPath('meta.has_data', false)->assertJsonPath('summary.fill_rate', null);
        GymService::factory()->forGym($this->gym)->count(2)->create();
        $this->occurrence('2026-11-02 23:59:59', ['capacity' => 0]);
        $this->occurrence('2026-11-03 00:00:00');
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('services').'?from=2026-11-02&to=2026-11-02')
            ->assertOk()->assertJsonPath('summary.sessions', 1)->assertJsonPath('summary.invalid_capacity_sessions', 1)
            ->assertJsonPath('summary.fill_rate', null)->assertJsonPath('summary.capacity', 0);
        $this->assertCount(3, $response->json('most_booked'));
        $this->assertCount(3, $response->json('least_booked'));
    }
}
