<?php

namespace Tests\Feature;

use App\Models\SessionBooking;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

class ProgressNoteTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
    }

    public function test_assigned_coach_can_add_session_and_participant_notes_and_roster_includes_them(): void
    {
        $this->actingAs($this->coach)->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => 'Practise footwork.'])->assertCreated()->assertJsonPath('note.user_id', null);
        $this->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => 'Keep your guard up.', 'user_id' => $this->applicant->id])->assertCreated();
        $this->getJson($this->url("sessions/{$this->sessionId}/roster"))->assertOk()->assertJsonCount(2, 'notes');
    }

    public function test_other_employee_without_manage_and_member_cannot_add_notes(): void
    {
        $employee = $this->employeeWith(['training' => 'view']);
        foreach ([$employee, $this->applicant] as $user) {
            $this->actingAs($user)->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => 'Not allowed.'])->assertForbidden();
        }
        $this->actingAs($this->owner)->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => 'Manager note.'])->assertCreated();
    }

    public function test_participant_must_be_booked_and_note_body_required(): void
    {
        $this->actingAs($this->coach)->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => 'Test', 'user_id' => $this->owner->id])->assertUnprocessable();
        $this->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => '   '])->assertUnprocessable();
        app(CurrentGym::class)->set($this->gym);
        SessionBooking::findOrFail($this->bookingId)->forceFill(['status' => 'waitlisted'])->save();
        $this->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => 'Test', 'user_id' => $this->applicant->id])->assertUnprocessable();
    }

    public function test_athlete_sees_only_own_participant_notes_in_record(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $other = User::factory()->create();
        $booking = SessionBooking::findOrFail($this->bookingId)->replicate();
        $booking->forceFill(['user_id' => $other->id])->save();
        foreach ([null => 'Whole class', $this->applicant->id => 'Your guard', $other->id => 'Private to another athlete'] as $id => $body) {
            $this->actingAs($this->coach)->postJson($this->url("sessions/{$this->sessionId}/notes"), ['body' => $body, 'user_id' => $id ?: null])->assertCreated();
        }
        $this->actingAs($this->applicant)->getJson($this->url('my-record'))->assertOk()->assertJsonCount(1, 'record.progress_notes')
            ->assertJsonPath('record.progress_notes.0.body', 'Your guard')->assertJsonMissing(['body' => 'Whole class'])->assertJsonMissing(['body' => 'Private to another athlete']);
        $this->actingAs($this->owner)->getJson($this->url("athletes/{$this->applicant->id}/record"))->assertOk()->assertJsonCount(1, 'record.progress_notes');
    }
}
