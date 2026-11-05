<?php

namespace Tests\Feature\Concerns;

use App\Models\EmployeeProfile;
use App\Models\Room;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;

trait BookingSetup
{
    use MembershipSetup;

    protected User $coach;

    protected Room $room;

    protected int $sessionId;

    protected int $bookingId;

    protected function setUpBooking(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $this->setUpMembershipGym();
        $this->active();
        $this->coach = $this->freeCoach();
        app(CurrentGym::class)->set($this->gym);
        $this->room = Room::factory()->create();
        $this->sessionId = $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->sessionData())->assertCreated()->json('session.id');
        $this->bookingId = $this->actingAs($this->applicant)->postJson($this->url("timetable/{$this->sessionId}/book"))->assertCreated()->json('booking.id');
    }

    protected function freeCoach(): User
    {
        $coach = $this->employeeWith(['training' => 'view']);
        app(CurrentGym::class)->set($this->gym);
        (new EmployeeProfile)->forceFill(['user_id' => $coach->id, 'coach_rank' => 'Coach'])->save();
        foreach (array_keys(config('hr.weekdays')) as $weekday) {
            WorkSchedule::create(['user_id' => $coach->id, 'weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '20:00']);
        }

        return $coach;
    }

    protected function sessionData(array $changes = []): array
    {
        return array_replace([
            'type' => 'classes', 'name' => 'Boxing', 'level' => 'all',
            'coach_id' => $this->coach->id, 'room_id' => $this->room->id,
            'starts_at' => '2026-11-09 10:00:00', 'ends_at' => '2026-11-09 11:00:00',
            'capacity' => 1, 'fee' => 150,
        ], $changes);
    }

    protected function bookingPayment(array $changes = [])
    {
        return $this->actingAs($this->owner)->postJson($this->url('payments'), array_replace([
            'session_booking_id' => $this->bookingId, 'amount' => 150, 'method' => 'cash',
        ], $changes));
    }

    protected function bookingPayload()
    {
        return $this->actingAs($this->applicant)->getJson('/api/my-bookings')->assertOk();
    }
}
