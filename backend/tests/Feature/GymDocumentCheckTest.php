<?php

namespace Tests\Feature;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-133: expiry dates and browser-read flags on verification files. */
class GymDocumentCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->applicant = User::factory()->owner()->create();
    }

    private function gym(): Gym
    {
        return Gym::factory()->application($this->applicant, 1)->create();
    }

    private function upload(Gym $gym, string $type, array $extra = [])
    {
        return $this->actingAs($this->applicant)->post("/api/gym-applications/{$gym->id}/documents", [
            'type' => $type,
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ...$extra,
        ], ['Accept' => 'application/json']);
    }

    private function doc(Gym $gym, string $type, $expires = null)
    {
        Storage::disk('local')->put("gym-applications/{$type}.pdf", 'pdf');

        return $gym->documents()->create(['type' => $type, 'path' => "gym-applications/{$type}.pdf", 'original_name' => "{$type}.pdf", 'mime' => 'application/pdf', 'size' => 3, 'expires_on' => $expires]);
    }

    public function test_expired_date_is_refused_and_future_date_saved(): void
    {
        $gym = $this->gym();

        $this->upload($gym, 'dti', ['expires_on' => today()->subDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors(['expires_on']);

        $next = today()->addYear()->toDateString();
        $this->upload($gym, 'dti', ['expires_on' => $next])->assertCreated()->assertJsonPath('document.expires_on', $next);
    }

    public function test_flags_are_saved_and_checked(): void
    {
        $gym = $this->gym();

        $this->upload($gym, 'dti', ['ocr_flags' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors(['ocr_flags.0']);
        $this->upload($gym, 'dti', ['ocr_flags' => ['owner_name']])->assertCreated()->assertJsonPath('document.ocr_flags', ['owner_name']);
    }

    public function test_type_without_expiry_saves_null(): void
    {
        $this->upload($this->gym(), 'sec', ['expires_on' => today()->addYear()->toDateString()])
            ->assertCreated()->assertJsonPath('document.expires_on', null);
    }

    public function test_owner_can_change_the_date_before_sending(): void
    {
        $gym = $this->gym();
        $doc = $this->doc($gym, 'dti', today()->addMonth());
        $url = "/api/gym-applications/{$gym->id}/documents/{$doc->id}";
        $next = today()->addYears(2)->toDateString();

        $this->actingAs($this->applicant)->putJson($url, ['expires_on' => $next])->assertOk()->assertJsonPath('document.expires_on', $next);
        $this->actingAs($this->applicant)->putJson($url, ['expires_on' => today()->subDay()->toDateString()])->assertUnprocessable();
        $this->actingAs(User::factory()->owner()->create())->putJson($url, ['expires_on' => $next])->assertNotFound();

        $gym->forceFill(['status' => GymStatus::Submitted])->save();
        $this->actingAs($this->applicant)->putJson($url, ['expires_on' => $next])->assertForbidden();
    }

    public function test_submit_needs_current_expiry_dates(): void
    {
        $gym = $this->gym();
        $this->doc($gym, 'dti', today()->addYear());
        $permit = $this->doc($gym, 'mayors_permit');
        $this->doc($gym, 'barangay_clearance', today()->addYear());
        $url = "/api/gym-applications/{$gym->id}/submit";

        $this->actingAs($this->applicant)->postJson($url)->assertUnprocessable()->assertJsonValidationErrors(["expiry.{$permit->id}"]);

        $permit->update(['expires_on' => today()->subDay()]);
        $this->actingAs($this->applicant)->postJson($url)->assertUnprocessable()->assertJsonValidationErrors(["expiry.{$permit->id}"]);

        $permit->update(['expires_on' => today()->addYear()]);
        $this->actingAs($this->applicant)->postJson($url)->assertOk();
    }
}
