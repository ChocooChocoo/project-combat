<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\PerformanceNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AttendanceSetup;
use Tests\TestCase;

/** R-58: notes need permission and are never shown to the employee they describe. */
class PerformanceNoteTest extends TestCase
{
    use AttendanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttendanceGym();
        $this->profileFor($this->employee);
    }

    private function urlFor(User $user): string
    {
        return $this->url("staff/{$user->id}/performance-notes");
    }

    public function test_a_permitted_person_adds_and_reads_a_note(): void
    {
        $this->actingAs($this->owner)->postJson($this->urlFor($this->employee), [
            'noted_on' => '2026-11-05',
            'kind' => 'commendation',
            'body' => 'Handled a difficult class well.',
        ])->assertCreated()
            ->assertJsonPath('note.kind', 'commendation')
            ->assertJsonPath('note.author_name', 'Owen Reyes');

        $this->actingAs($this->owner)->getJson($this->urlFor($this->employee))
            ->assertOk()
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.body', 'Handled a difficult class well.');
    }

    public function test_a_plain_employee_cannot_read_notes(): void
    {
        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Employee);

        $this->actingAs($other)->getJson($this->urlFor($this->employee))->assertForbidden();
    }

    public function test_the_subject_never_sees_their_own_notes_even_with_permission(): void
    {
        $this->giveRole($this->employee, ['hr.performance.view'], 'HR viewer');

        $this->actingAs($this->employee)->getJson($this->urlFor($this->employee))->assertForbidden();
    }

    public function test_nobody_writes_a_note_about_themselves(): void
    {
        $this->giveRole($this->employee, ['hr.performance.manage'], 'HR officer');

        $this->actingAs($this->employee)->postJson($this->urlFor($this->employee), [
            'noted_on' => '2026-11-05',
            'kind' => 'general',
            'body' => 'I did well.',
        ])->assertForbidden();
    }

    public function test_a_viewer_without_manage_cannot_write(): void
    {
        $viewer = User::factory()->create();
        $this->gym->addPerson($viewer, GymPersonType::Employee);
        $this->giveRole($viewer, ['hr.performance.view'], 'HR viewer');

        $this->actingAs($viewer)->postJson($this->urlFor($this->employee), [
            'noted_on' => '2026-11-05',
            'kind' => 'general',
            'body' => 'A note.',
        ])->assertForbidden();

        $this->assertSame(0, PerformanceNote::count());
    }
}
