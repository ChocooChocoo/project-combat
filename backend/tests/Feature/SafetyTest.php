<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\StockItem;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-119 injuries (coaches see only a restricted flag), (R-147) proof documents, R-120 equipment safety checks. */
class SafetyTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 09:00:00'));
        $this->setUpMembershipGym();
        $this->active();
    }

    private function report()
    {
        return $this->actingAs($this->applicant)->postJson($this->url("athletes/{$this->applicant->id}/injuries"), [
            'body_part' => 'Left knee', 'severity' => 'moderate', 'occurred_on' => '2026-11-18', 'description' => 'Twisted in sparring',
        ]);
    }

    public function test_athlete_reports_and_coach_sees_only_the_flag(): void
    {
        $id = $this->report()->assertCreated()->json('injuries.0.id');
        $this->assertTrue($this->owner->notifications()->where('data->title', 'Injury reported')->exists());

        $coach = $this->employeeWith(['training' => 'manage', 'members' => 'view']);
        $this->actingAs($coach)->getJson($this->url("athletes/{$this->applicant->id}/injuries"))->assertForbidden();
        $this->actingAs($coach)->putJson($this->url("injuries/{$id}"), ['status' => 'cleared'])->assertForbidden();
        $row = collect($this->actingAs($coach)->getJson($this->url('memberships?view=members'))->assertOk()->json('memberships'))
            ->firstWhere('member.id', $this->applicant->id);
        $this->assertTrue($row['restricted']);
        $this->assertArrayNotHasKey('injuries', $row);

        $nurse = $this->employeeWith(['health_records.records.view', 'health_records.injuries.manage']);
        $this->actingAs($nurse)->getJson($this->url("athletes/{$this->applicant->id}/injuries"))->assertJsonPath('injuries.0.body_part', 'Left knee');
    }

    public function test_recovery_log_and_clearing_tells_the_athlete(): void
    {
        $id = $this->report()->json('injuries.0.id');
        $this->actingAs($this->owner)->putJson($this->url("injuries/{$id}"), ['status' => 'recovering', 'note' => 'Light drills only', 'restrictions' => 'No sparring'])
            ->assertJsonPath('injuries.0.status', 'recovering')->assertJsonPath('injuries.0.restrictions', 'No sparring');
        $this->actingAs($this->owner)->putJson($this->url("injuries/{$id}"), ['status' => 'cleared'])
            ->assertJsonPath('injuries.0.cleared_on', '2026-11-20')->assertJsonCount(3, 'injuries.0.updates');
        $this->assertTrue($this->applicant->notifications()->where('data->title', 'Cleared to train')->exists());

        $row = collect($this->actingAs($this->owner)->getJson($this->url('memberships?view=members'))->json('memberships'))->firstWhere('member.id', $this->applicant->id);
        $this->assertFalse($row['restricted']);
    }

    /** (R-147) a report may carry a medical certificate or other proof, opened by the member or health staff. */
    public function test_a_report_can_carry_proof_documents(): void
    {
        Storage::fake('local');
        $reported = $this->actingAs($this->applicant)->post($this->url("athletes/{$this->applicant->id}/injuries"), [
            'body_part' => 'Left knee', 'severity' => 'moderate', 'occurred_on' => '2026-11-18',
            'files' => [
                UploadedFile::fake()->create('medical-certificate.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('x-ray.jpg', 80, 'image/jpeg'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('injuries.0.files.0.name', 'medical-certificate.pdf')
            ->assertJsonPath('injuries.0.files.1.name', 'x-ray.jpg');

        $id = $reported->json('injuries.0.id');
        $first = $reported->json('injuries.0.files.0.id');

        $this->actingAs($this->applicant)->get($this->url("injuries/{$id}/files/{$first}"))->assertOk();
        $this->actingAs($this->owner)->get($this->url("injuries/{$id}/files/{$first}"))->assertOk();

        $coach = $this->employeeWith(['training' => 'manage', 'members' => 'view']);
        $this->actingAs($coach)->get($this->url("injuries/{$id}/files/{$first}"))->assertForbidden();

        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Member);
        $this->actingAs($other)->get($this->url("injuries/{$id}/files/{$first}"))->assertForbidden();
    }

    public function test_too_many_or_wrong_proof_files_are_refused(): void
    {
        Storage::fake('local');
        $payload = fn (array $files) => [
            'body_part' => 'Left knee', 'severity' => 'moderate', 'occurred_on' => '2026-11-18', 'files' => $files,
        ];

        $this->actingAs($this->applicant)->post($this->url("athletes/{$this->applicant->id}/injuries"), $payload([
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertJsonValidationErrors('files');

        $this->actingAs($this->applicant)->post($this->url("athletes/{$this->applicant->id}/injuries"), $payload([
            UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]), ['Accept' => 'application/json'])->assertJsonValidationErrors('files.0');

        $this->actingAs($this->applicant)->post($this->url("athletes/{$this->applicant->id}/injuries"), $payload([]), ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonCount(0, 'injuries.0.files');
    }

    public function test_checks_update_status_and_unsafe_alerts_the_owner(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $bag = app(StockLedger::class)->open($this->gym, ['name' => 'Heavy bag', 'category' => 'equipment', 'unit' => 'piece', 'location' => 'Floor'], 2, $this->owner);
        $wraps = app(StockLedger::class)->open($this->gym, ['name' => 'Wraps', 'category' => 'merchandise', 'unit' => 'pair', 'location' => 'Desk'], 5, $this->owner);

        $this->actingAs($this->owner)->putJson($this->url("equipment-safety/{$bag->id}"), ['check_interval_days' => 30])->assertJsonPath('item.next_check_on', '2026-12-20');
        $this->actingAs($this->owner)->postJson($this->url("equipment-safety/{$wraps->id}/checks"), ['result' => 'pass'])->assertNotFound();
        $this->actingAs($this->owner)->postJson($this->url("equipment-safety/{$bag->id}/checks"), ['result' => 'unsafe'])->assertJsonValidationErrors('note');

        $this->actingAs($this->owner)->postJson($this->url("equipment-safety/{$bag->id}/checks"), ['result' => 'unsafe', 'note' => 'Chain is cracked'])
            ->assertOk()->assertJsonPath('item.safety_status', 'unsafe')->assertJsonPath('item.next_check_on', '2026-12-20');
        $this->assertTrue($this->owner->notifications()->where('data->title', 'Unsafe equipment')->exists());

        $this->actingAs($this->owner)->getJson($this->url('equipment-safety'))->assertJsonCount(1, 'items')->assertJsonPath('items.0.checks.0.note', 'Chain is cracked');
        $staff = $this->employeeWith(['inventory.items.view']);
        $this->actingAs($staff)->getJson($this->url('equipment-safety'))->assertForbidden();
    }

    public function test_due_digest_is_sent_once_per_due_date(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $bag = app(StockLedger::class)->open($this->gym, ['name' => 'Heavy bag', 'category' => 'equipment', 'unit' => 'piece', 'location' => 'Floor'], 2, $this->owner);
        StockItem::whereKey($bag->id)->update(['check_interval_days' => 7, 'next_check_on' => '2026-11-19']);

        $this->artisan('app:remind-equipment-checks')->expectsOutput('1 equipment checks due.')->assertSuccessful();
        $this->app->forgetScopedInstances();
        $this->artisan('app:remind-equipment-checks')->expectsOutput('0 equipment checks due.')->assertSuccessful();

        // A pass moves the due date, so the next due date is told again.
        $this->actingAs($this->owner)->postJson($this->url("equipment-safety/{$bag->id}/checks"), ['result' => 'pass', 'checked_on' => '2026-11-10'])->assertOk();
        $this->app->forgetScopedInstances();
        $this->artisan('app:remind-equipment-checks')->expectsOutput('1 equipment checks due.')->assertSuccessful();
    }
}
