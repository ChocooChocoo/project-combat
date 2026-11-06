<?php

namespace Tests\Feature\Concerns;

use App\Enums\GymPersonType;
use App\Enums\PaymentMethod;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Room;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Support\Carbon;

/** Shared fixtures for the Packet 6D finance tests. */
trait FinanceSetup
{
    use GivesGymRoles;

    protected Gym $gym;

    protected User $owner;

    protected User $member;

    protected MembershipPlan $plan;

    protected function setUpFinanceGym(): void
    {
        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create(['first_name' => 'Owen', 'last_name' => 'Reyes']);
        $this->member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Torres']);

        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->member, GymPersonType::Member);

        app(CurrentGym::class)->set($this->gym);

        $this->plan = MembershipPlan::factory()->forGym($this->gym)->create([
            'published_at' => now(),
            'total_fee' => 1000,
            'billing_type' => 'time',
            'duration_months' => 1,
        ]);
    }

    protected function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    protected function giveFinanceRole(User $user, array $permissions, string $name = 'Finance role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, $name.' '.$user->id)->users()->attach($user->id);
        $this->app->forgetScopedInstances();
        app(CurrentGym::class)->set($this->gym);
    }

    protected function employeeWithFinanceRole(array $permissions): User
    {
        $user = User::factory()->create(['first_name' => 'Faye', 'last_name' => 'Cruz']);
        $this->gym->addPerson($user, GymPersonType::Employee);
        $this->giveFinanceRole($user, $permissions, 'Finance test role');

        return $user;
    }

    protected function makeMembership(User $user, array $overrides = []): Membership
    {
        app(CurrentGym::class)->set($this->gym);

        $membership = new Membership;
        $membership->fill([
            'user_id' => $user->id,
            'membership_plan_id' => $this->plan->id,
            'emergency_contacts' => [['name' => 'Maria Cruz', 'relationship' => 'Parent', 'phone' => '0917 123 4567']],
            'health_answers' => array_fill_keys(array_keys(config('membership.health_questions')), false),
            'waivers' => array_keys(config('membership.waivers')),
            'signature_name' => trim("{$user->first_name} {$user->last_name}"),
            'signed_on' => today()->toDateString(),
        ]);
        $membership->forceFill(array_replace([
            'status' => 'active',
            'amount_charged' => 1000,
        ], $overrides))->save();

        return $membership->refresh();
    }

    protected function makeSession(string $type = 'classes', string $fee = '150.00', string $name = 'Boxing'): TrainingSession
    {
        app(CurrentGym::class)->set($this->gym);
        $room = Room::factory()->forGym($this->gym)->create();

        return TrainingSession::create([
            'type' => $type,
            'name' => $name,
            'discipline' => 'boxing',
            'level' => 'all',
            'coach_id' => $this->owner->id,
            'room_id' => $room->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'capacity' => 8,
            'fee' => $fee,
            'needs_approval' => false,
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);
    }

    protected function makeBooking(
        User $user,
        TrainingSession $session,
        string $status = 'booked',
        string $fee = '150.00',
        ?Membership $membership = null,
    ): SessionBooking {
        app(CurrentGym::class)->set($this->gym);

        return SessionBooking::forceCreate([
            'training_session_id' => $session->id,
            'user_id' => $user->id,
            'membership_id' => $membership?->id,
            'status' => $status,
            'fee' => $fee,
        ]);
    }

    protected function payMembership(Membership $membership, string $amount, PaymentMethod $method = PaymentMethod::Cash, ?Carbon $paidAt = null): Payment
    {
        app(CurrentGym::class)->set($this->gym);
        $payment = Payment::create([
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'amount' => $amount,
            'method' => $method,
            'paid_at' => $paidAt ?? now(),
        ]);
        $payment->receipt_number = sprintf('OR-%d-%06d', $payment->gym_id, $payment->id);
        $payment->save();

        return $payment;
    }

    protected function payBooking(SessionBooking $booking, string $amount, PaymentMethod $method = PaymentMethod::Cash, ?Carbon $paidAt = null): Payment
    {
        app(CurrentGym::class)->set($this->gym);
        $payment = Payment::create([
            'session_booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount' => $amount,
            'method' => $method,
            'paid_at' => $paidAt ?? now(),
        ]);
        $payment->receipt_number = sprintf('OR-%d-%06d', $payment->gym_id, $payment->id);
        $payment->save();

        return $payment;
    }
}
