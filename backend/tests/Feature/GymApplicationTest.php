<?php

namespace Tests\Feature;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-11 to R-15: owner application, tiers, upload rules, following it, private files. */
class GymApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->applicant = User::factory()->create();
    }

    private function application(int $tier = 1): Gym
    {
        return Gym::factory()->application($this->applicant, $tier)->create();
    }

    private function upload(Gym $gym, string $type, ?UploadedFile $file = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->applicant)->post("/api/gym-applications/{$gym->id}/documents", [
            'type' => $type,
            'file' => $file ?? UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
    }

    private function uploadTier1(Gym $gym): void
    {
        foreach (['dti', 'mayors_permit', 'barangay_clearance'] as $type) {
            $this->upload($gym, $type)->assertCreated();
        }
    }

    private function submit(Gym $gym)
    {
        return $this->actingAs($this->applicant)->postJson("/api/gym-applications/{$gym->id}/submit");
    }

    public function test_only_a_confirmed_user_can_apply(): void
    {
        $unconfirmed = User::factory()->unverified()->create();
        $this->actingAs($unconfirmed)->postJson('/api/gym-applications', ['name' => 'Iron Gym'])->assertForbidden();

        $this->actingAs($this->applicant)->postJson('/api/gym-applications', ['name' => 'Iron Gym', 'tier' => 1])
            ->assertCreated()
            ->assertJsonPath('application.status', 'draft')
            ->assertJsonPath('application.applicant_id', $this->applicant->id);
    }

    public function test_requirements_list_each_tier(): void
    {
        $this->actingAs($this->applicant)->getJson('/api/gym-applications/requirements')
            ->assertOk()
            ->assertJsonPath('tiers.1.requirements.0.types', ['dti', 'sec'])
            ->assertJsonPath('tiers.2.requirements.3.min', 10);
    }

    public function test_incomplete_application_cannot_be_sent(): void
    {
        $gym = Gym::factory()->create(['applicant_id' => $this->applicant->id, 'tier' => 1]);

        $this->submit($gym)->assertUnprocessable()
            ->assertJsonValidationErrors(['address_line', 'latitude', 'disciplines', 'documents.dti_or_sec', 'documents.mayors_permit'])
            ->assertJsonMissingValidationErrors(['longitude']);
        $this->assertSame(GymStatus::Draft, $gym->fresh()->status);
    }

    public function test_tier_1_needs_dti_or_sec_and_both_permits(): void
    {
        $gym = $this->application();
        $this->upload($gym, 'sec')->assertCreated();
        $this->upload($gym, 'mayors_permit')->assertCreated();

        $this->submit($gym)->assertUnprocessable()->assertJsonValidationErrors(['documents.barangay_clearance'])
            ->assertJsonMissingValidationErrors(['documents.dti_or_sec']);

        $this->upload($gym, 'barangay_clearance')->assertCreated();
        $this->submit($gym)->assertOk()->assertJsonPath('application.status', 'submitted');
    }

    public function test_wrong_file_type_and_other_tier_documents_are_refused(): void
    {
        $gym = $this->application();

        $this->upload($gym, 'dti', UploadedFile::fake()->create('doc.docx', 10, 'application/msword'))
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);
        $this->upload($gym, 'owner_id')->assertUnprocessable()->assertJsonValidationErrors(['type']);

        $tier2 = $this->application(2);
        $this->upload($tier2, 'video_tour')->assertUnprocessable()->assertJsonValidationErrors(['file']);
        $this->upload($tier2, 'video_tour', UploadedFile::fake()->create('tour.mp4', 500, 'video/mp4'))->assertCreated();
    }

    public function test_a_file_over_the_size_limit_is_refused_with_a_clear_message(): void
    {
        $gym = $this->application();

        $this->upload($gym, 'dti', UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'This file is too big. The largest accepted size is 10 MB.']);
    }

    public function test_tier_2_needs_at_least_ten_photos(): void
    {
        $gym = $this->application(2);
        $this->uploadTier1($gym);
        $this->upload($gym, 'video_tour', UploadedFile::fake()->create('tour.mp4', 500, 'video/mp4'))->assertCreated();
        foreach (['owner_id', 'owner_selfie', 'utility_bill'] as $type) {
            $this->upload($gym, $type, UploadedFile::fake()->create('x.jpg', 50, 'image/jpeg'))->assertCreated();
        }
        for ($i = 0; $i < 9; $i++) {
            $this->upload($gym, 'facility_photo', UploadedFile::fake()->create("p{$i}.jpg", 50, 'image/jpeg'))->assertCreated();
        }

        $this->submit($gym)->assertUnprocessable()->assertJsonValidationErrors(['documents.facility_photo']);

        $this->upload($gym, 'facility_photo', UploadedFile::fake()->create('p9.jpg', 50, 'image/jpeg'))->assertCreated();
        $this->submit($gym)->assertOk();
    }

    public function test_documents_open_only_for_the_applicant_and_the_administrator(): void
    {
        $gym = $this->application();
        $id = $this->upload($gym, 'dti')->json('document.id');
        $url = "/api/gym-applications/{$gym->id}/documents/{$id}";

        $this->actingAs($this->applicant)->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson("/api/gym-applications/{$gym->id}")->assertNotFound();

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($admin)->get($url)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_files_cannot_be_deleted_after_sending(): void
    {
        $gym = $this->application();
        $this->uploadTier1($gym);
        $id = $gym->documents()->first()->id;
        $this->submit($gym)->assertOk();

        $this->actingAs($this->applicant)->deleteJson("/api/gym-applications/{$gym->id}/documents/{$id}")->assertForbidden();
        $this->actingAs($this->applicant)->putJson("/api/gym-applications/{$gym->id}", ['name' => 'New'])->assertForbidden();
        $this->assertSame(3, $gym->documents()->count());
    }

    public function test_applicant_deletes_own_file_before_sending(): void
    {
        $gym = $this->application();
        $document = $this->upload($gym, 'dti')->json('document');
        $path = $gym->documents()->first()->path;

        $this->actingAs(User::factory()->create())->deleteJson("/api/gym-applications/{$gym->id}/documents/{$document['id']}")->assertNotFound();
        $this->actingAs($this->applicant)->deleteJson("/api/gym-applications/{$gym->id}/documents/{$document['id']}")->assertOk();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_changing_tier_keeps_uploaded_files(): void
    {
        $gym = $this->application(2);
        $this->upload($gym, 'owner_id', UploadedFile::fake()->create('id.jpg', 50, 'image/jpeg'))->assertCreated();

        $this->actingAs($this->applicant)->putJson("/api/gym-applications/{$gym->id}", ['tier' => 1])
            ->assertOk()->assertJsonCount(1, 'application.documents');
    }

    public function test_applicant_is_not_an_owner_after_sending(): void
    {
        $gym = $this->application();
        $this->uploadTier1($gym);
        $this->submit($gym)->assertOk();

        $this->assertDatabaseMissing('gym_user', ['gym_id' => $gym->id, 'user_id' => $this->applicant->id]);
        $this->actingAs($this->applicant)->getJson('/api/gym-applications')
            ->assertOk()->assertJsonPath('applications.0.status', 'submitted');
    }

    public function test_operating_hours_must_close_after_opening(): void
    {
        $gym = $this->application();

        $this->actingAs($this->applicant)->putJson("/api/gym-applications/{$gym->id}", [
            'operating_hours' => ['mon' => ['open' => '20:00', 'close' => '08:00']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['operating_hours.mon.close' => 'Closing time must be after opening time.']);

        $this->actingAs($this->applicant)->putJson("/api/gym-applications/{$gym->id}", [
            'operating_hours' => ['funday' => ['open' => '08:00', 'close' => '09:00']],
        ])->assertUnprocessable();
    }
}
