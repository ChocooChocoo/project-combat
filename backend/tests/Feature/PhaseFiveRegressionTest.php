<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\PaymentMethod;
use App\Models\EmployeeProfile;
use App\Models\GymService;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\CheckInCode;
use App\Support\CurrentGym;
use App\Support\MembershipLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

class PhaseFiveRegressionTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
    }

    public function test_manila_check_in_finds_upcoming_booking_and_enforces_cancellation_cutoff(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->travelTo(Carbon::parse('2026-11-09T01:57:00Z'));
        $this->assertSame('09:57', now()->format('H:i'));
        $this->gym->forceFill(['cancel_cutoff_hours' => 2])->save();
        $this->actingAs($this->applicant)->postJson($this->url("bookings/{$this->bookingId}/cancel"))
            ->assertUnprocessable()->assertSee('cut-off');
        $code = app(CheckInCode::class)->issue($this->gym, $this->applicant)['payload'];
        $desk = $this->employeeWith(config('access.templates.front_desk'));
        $this->actingAs($desk)->postJson($this->url('check-ins'), ['code' => $code])
            ->assertCreated()->assertJsonPath('check_in.attendance', 'present');
        $this->assertDatabaseHas('session_bookings', ['id' => $this->bookingId, 'check_in_at' => '2026-11-09 09:57:00.000000']);
        $this->assertDatabaseCount('gym_visits', 0);
        $this->bookingPayload()->assertJsonPath('upcoming.0.check_in_at', '2026-11-09 09:57:00');
        $this->bookingPayment()->assertCreated()->assertJsonPath('payment.paid_at', '2026-11-09T09:57:00+08:00');
    }

    public function test_coach_lists_and_validation_require_profile_not_training_access(): void
    {
        $desk = $this->employeeWith(['training' => 'manage']);
        $this->actingAs($this->owner)->getJson($this->url('sessions/options'))
            ->assertOk()->assertJsonCount(1, 'options.coaches')->assertJsonPath('options.coaches.0.id', $this->coach->id);
        $this->getJson($this->url('timetable'))->assertOk()->assertJsonCount(1, 'coaches');
        $this->getJson($this->url("sessions/{$this->sessionId}/free-coaches"))
            ->assertOk()->assertJsonCount(1, 'coaches')->assertJsonPath('coaches.0.id', $this->coach->id);
        $this->postJson($this->url('sessions'), $this->sessionData(['coach_id' => $desk->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('coach_id');
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['coach_id' => $this->owner->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('coach_id');
        app(CurrentGym::class)->set($this->gym);
        $service = GymService::factory()->create(['activity_type' => 'private_coaching']);
        $this->getJson($this->url("coaches/{$desk->id}/open-slots?date=2026-11-09&service_id={$service->id}"))->assertNotFound();
        $this->postJson($this->url('private-bookings'), ['gym_service_id' => $service->id, 'coach_id' => $desk->id, 'starts_at' => '2026-11-09 12:00:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('coach_id');
        app(CurrentGym::class)->set($this->gym);
        (new EmployeeProfile)->forceFill(['user_id' => $this->owner->id, 'coach_bio' => 'Owner also coaches'])->save();
        $this->getJson($this->url('sessions/options'))->assertOk()->assertJsonCount(2, 'options.coaches');
        $this->gym->people()->wherePivot('role', GymPersonType::Employee->value)->detach($this->coach->id);
        $this->getJson($this->url('sessions/options'))->assertOk()->assertJsonCount(1, 'options.coaches')->assertJsonPath('options.coaches.0.id', $this->owner->id);
    }

    public function test_inactive_booking_states_do_not_owe_fees_and_cancelled_bookings_are_past(): void
    {
        foreach (['waitlisted', 'rejected', 'cancelled'] as $status) {
            SessionBooking::withoutGlobalScope('gym')->whereKey($this->bookingId)->update(['status' => $status]);
            $group = $status === 'waitlisted' ? 'upcoming' : 'past';
            $this->bookingPayload()->assertJsonPath("{$group}.0.payment_state", 'none')->assertJsonPath("{$group}.0.balance", '0.00');
        }
    }

    public function test_private_coaching_never_accepts_waitlist(): void
    {
        TrainingSession::withoutGlobalScope('gym')->whereKey($this->sessionId)->update(['type' => 'private_coaching']);
        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Member);
        app(CurrentGym::class)->set($this->gym);
        $membership = SessionBooking::findOrFail($this->bookingId)->membership->replicate();
        $membership->user_id = $other->id;
        $membership->save();
        $this->actingAs($other)->getJson($this->url("timetable/{$this->sessionId}"))
            ->assertOk()->assertJsonPath('session.waiting_list_open', false);
        $this->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertDatabaseCount('session_bookings', 1);
    }

    public function test_cancelled_booking_with_money_received_still_shows_payment(): void
    {
        $this->bookingPayment(['amount' => 50])->assertCreated();
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$this->bookingId}/cancel"))->assertOk();
        $this->bookingPayload()->assertJsonCount(0, 'upcoming')->assertJsonPath('past.0.payment_state', 'paid')
            ->assertJsonPath('past.0.paid', '50.00')->assertJsonPath('past.0.balance', '0.00');
    }

    public function test_membership_payment_cannot_exceed_partial_or_zero_balance(): void
    {
        $membership = SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->membership;
        $body = ['membership_id' => $membership->id, 'amount' => 2800, 'method' => 'cash'];
        $this->actingAs($this->owner)->postJson($this->url('payments'), $body)
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        app(CurrentGym::class)->set($this->gym);
        app(MembershipLedger::class)->recordPayment($membership, -500, PaymentMethod::Cash, $this->owner);
        $this->postJson($this->url('payments'), [...$body, 'amount' => 501])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson($this->url('payments'), [...$body, 'amount' => 500])->assertCreated();
    }
}
