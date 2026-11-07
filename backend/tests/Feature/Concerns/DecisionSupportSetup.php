<?php

namespace Tests\Feature\Concerns;

trait DecisionSupportSetup
{
    use FinanceSetup;

    protected function setUpDecisionSupport(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-07 00:15:00', 'Asia/Manila'));
        $this->setUpFinanceGym();
    }

    protected function decisionUrl(string $view = 'options'): string
    {
        return $this->url('decision-support/'.$view);
    }

    protected function occurrence(string $start = '2026-11-02 10:00:00', array $attributes = []): \App\Models\TrainingSession
    {
        $session = $this->makeSession();
        $session->update(array_replace([
            'starts_at' => $start,
            'ends_at' => \Illuminate\Support\Carbon::parse($start)->addHour(),
        ], $attributes));

        return $session->refresh();
    }

    protected function markedBooking(\App\Models\TrainingSession $session, ?string $marker, string $status = 'booked', ?\App\Models\User $user = null): \App\Models\SessionBooking
    {
        $booking = $this->makeBooking($user ?? \App\Models\User::factory()->create(), $session, $status);
        \Illuminate\Support\Facades\DB::table('session_bookings')->where('id', $booking->id)->update(['attendance' => $marker]);

        return $booking;
    }

    protected function progress(\App\Models\User $user, string $type = 'promotion', array $attributes = []): \App\Models\AthleteEntry
    {
        return \App\Models\AthleteEntry::create(array_replace([
            'user_id' => $user->id, 'type' => $type, 'title' => 'Stored title',
            'discipline' => 'boxing', 'occurred_on' => '2026-11-02',
            'recorded_by' => $this->owner->id, 'notes' => 'PRIVATE NOTE MUST NOT LEAK',
        ], $attributes));
    }

    protected function membershipEvent(\App\Models\Membership $membership, string $type, string $at): int
    {
        return \Illuminate\Support\Facades\DB::table('membership_events')->insertGetId([
            'gym_id' => $this->gym->id, 'membership_id' => $membership->id, 'type' => $type,
            'by_user_id' => $this->owner->id, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    protected function customerAt(string $at, ?\App\Models\Membership $membership = null, ?string $linkedAt = null): \App\Models\Customer
    {
        return \App\Models\Customer::forceCreate([
            'name' => 'Fixture lead', 'source' => 'walk_in', 'status' => 'open',
            'membership_id' => $membership?->id, 'user_id' => $membership?->user_id,
            'linked_at' => $linkedAt, 'created_at' => $at, 'updated_at' => $at,
            'created_by' => $this->owner->id, 'inquiry' => 'PRIVATE INQUIRY',
        ]);
    }

    protected function income(string $type, string $amount, string $at = '2026-11-02 10:00:00', array $attributes = []): \App\Models\Payment
    {
        return \App\Models\Payment::create(array_replace([
            'user_id' => $this->member->id, 'income_type' => $type, 'amount' => $amount,
            'method' => 'cash', 'paid_at' => $at,
        ], $attributes));
    }

    protected function payrollFixture(string $end, string $status = 'released', array $lines = []): \App\Models\PayrollRun
    {
        $run = $this->gym->payrollRuns()->create([
            'starts_on' => substr($end, 0, 7).'-01', 'ends_on' => $end, 'status' => $status,
            'prepared_by' => $this->owner->id, 'gross_total' => '999999.00', 'net_total' => '888888.00',
            'employer_statutory_total' => '777777.00',
        ]);
        foreach ($lines as $line) {
            $run->lines()->create(array_replace([
                'user_id' => \App\Models\User::factory()->create()->id, 'name' => 'Payroll fixture employee',
                'monthly_rate' => '123456.00', 'warnings' => ['PRIVATE WARNING'],
            ], $line));
        }

        return $run;
    }
}
