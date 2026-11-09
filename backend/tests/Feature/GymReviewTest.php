<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\GymApplicationDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-16 checklist, R-17 decisions, R-18 going live, R-19 platform numbers. */
class GymReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->applicant = User::factory()->owner()->create();
    }

    private function sent(int $tier = 1): Gym
    {
        return Gym::factory()->application($this->applicant, $tier)->create([
            'status' => GymStatus::Submitted, 'submitted_at' => now(),
        ]);
    }

    private function pass(Gym $gym, array $items): void
    {
        $this->actingAs($this->admin)->putJson("/api/admin/applications/{$gym->id}/checklist", [
            'checklist' => array_fill_keys($items, true),
        ])->assertOk();
    }

    private function decide(Gym $gym, array $body)
    {
        return $this->actingAs($this->admin)->postJson("/api/admin/applications/{$gym->id}/decision", $body);
    }

    public function test_non_admin_is_refused_on_every_admin_route(): void
    {
        $gym = $this->sent();
        $this->actingAs($this->applicant);

        $this->getJson('/api/admin/stats')->assertForbidden();
        $this->getJson('/api/admin/applications')->assertForbidden();
        $this->getJson("/api/admin/applications/{$gym->id}")->assertForbidden();
        $this->putJson("/api/admin/applications/{$gym->id}/checklist", ['checklist' => []])->assertForbidden();
        $this->postJson("/api/admin/applications/{$gym->id}/decision", ['decision' => 'approve'])->assertForbidden();
    }

    public function test_queue_lists_sent_applications_and_opening_one_starts_review(): void
    {
        $gym = $this->sent();
        Gym::factory()->application($this->applicant)->create(); // a draft, never listed

        $this->actingAs($this->admin)->getJson('/api/admin/applications')->assertOk()->assertJsonCount(1, 'applications');
        $this->actingAs($this->admin)->getJson("/api/admin/applications/{$gym->id}")->assertOk()
            ->assertJsonPath('application.status', 'under_review')
            ->assertJsonPath('checklist_items', ['documents_complete', 'permits_current', 'details_match']);
    }

    public function test_admin_opens_the_documents(): void
    {
        $gym = $this->sent();
        Storage::disk('local')->put('gym-applications/x.pdf', 'pdf');
        $document = $gym->documents()->create(['type' => 'dti', 'path' => 'gym-applications/x.pdf', 'original_name' => 'dti.pdf', 'mime' => 'application/pdf', 'size' => 3]);

        $this->actingAs($this->admin)->get("/api/gym-applications/{$gym->id}/documents/{$document->id}")->assertOk();
    }

    public function test_approve_needs_every_check_passed_and_tier_2_has_two_more(): void
    {
        $gym = $this->sent(2);

        $this->decide($gym, ['decision' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors(['checklist']);

        $this->pass($gym, ['documents_complete', 'permits_current', 'details_match']);
        $this->actingAs($this->admin)->putJson("/api/admin/applications/{$gym->id}/checklist", ['checklist' => ['selfie_matches_id' => false]])->assertOk();
        $this->decide($gym, ['decision' => 'approve'])->assertUnprocessable();

        $this->pass($gym, ['selfie_matches_id', 'facility_matches']);
        $this->decide($gym, ['decision' => 'approve'])->assertOk()->assertJsonPath('application.status', 'approved');
        $this->assertSame(2, $gym->fresh()->approved_tier);
    }

    public function test_tier_1_checklist_refuses_tier_2_items(): void
    {
        $gym = $this->sent(1);

        $this->actingAs($this->admin)->putJson("/api/admin/applications/{$gym->id}/checklist", ['checklist' => ['selfie_matches_id' => true]])
            ->assertUnprocessable();
    }

    public function test_reject_needs_a_reason(): void
    {
        $gym = $this->sent();

        $this->decide($gym, ['decision' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->decide($gym, ['decision' => 'reject', 'reason' => 'Permit is for another business.'])->assertOk();
        $this->assertSame(GymStatus::Rejected, $gym->fresh()->status);

        // A decided application cannot be decided again.
        $this->decide($gym, ['decision' => 'return', 'items' => ['x']])->assertStatus(409);
    }

    public function test_returned_application_can_be_fixed_and_sent_again(): void
    {
        $gym = $this->sent();
        foreach (['dti', 'mayors_permit', 'barangay_clearance'] as $type) {
            Storage::disk('local')->put("gym-applications/{$type}.pdf", 'pdf');
            $gym->documents()->create(['type' => $type, 'path' => "gym-applications/{$type}.pdf", 'original_name' => "{$type}.pdf", 'mime' => 'application/pdf', 'size' => 3]);
        }

        $this->decide($gym, ['decision' => 'return'])->assertUnprocessable()->assertJsonValidationErrors(['items']);
        $this->decide($gym, ['decision' => 'return', 'items' => ['Barangay clearance is blurry']])->assertOk();

        $this->actingAs($this->applicant)->getJson("/api/gym-applications/{$gym->id}")
            ->assertJsonPath('application.status', 'returned')
            ->assertJsonPath('application.return_items', ['Barangay clearance is blurry']);

        $old = $gym->documents()->where('type', 'barangay_clearance')->first();
        $this->actingAs($this->applicant)->deleteJson("/api/gym-applications/{$gym->id}/documents/{$old->id}")->assertOk();
        $this->actingAs($this->applicant)->post("/api/gym-applications/{$gym->id}/documents", [
            'type' => 'barangay_clearance', 'file' => UploadedFile::fake()->create('clear.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($this->applicant)->postJson("/api/gym-applications/{$gym->id}/submit")->assertOk()
            ->assertJsonPath('application.status', 'submitted');
    }

    public function test_applicant_gets_one_notice_per_decision(): void
    {
        $returned = $this->sent();
        $this->decide($returned, ['decision' => 'return', 'items' => ['Upload a clearer permit']])->assertOk();

        $rejected = $this->sent();
        $this->decide($rejected, ['decision' => 'reject', 'reason' => 'Not a gym.'])->assertOk();

        $approved = $this->sent();
        $this->pass($approved, ['documents_complete', 'permits_current', 'details_match']);
        $this->decide($approved, ['decision' => 'approve'])->assertOk();

        $notices = $this->applicant->notifications()->where('type', GymApplicationDecided::class)->get();
        $this->assertCount(3, $notices);
        $this->assertEqualsCanonicalizing(['returned', 'rejected', 'approved'], $notices->pluck('data.status')->all());
        $this->assertNotEmpty($notices->firstWhere('data.status', 'rejected')->data['message']);
    }

    public function test_approved_applicant_becomes_owner_and_gym_goes_live_with_badge(): void
    {
        $gym = $this->sent();
        $this->pass($gym, ['documents_complete', 'permits_current', 'details_match']);
        $this->decide($gym, ['decision' => 'approve'])->assertOk();

        $this->assertTrue($this->applicant->hasGymRole($gym, GymPersonType::Owner));
        $this->assertSame(4, \App\Models\GymRole::withoutGlobalScope('gym')->where('gym_id', $gym->id)->where('is_system', true)->count());
        $this->actingAs($this->applicant)->getJson("/api/gyms/{$gym->id}/roles")->assertOk();

        $this->sent(); // still pending
        Gym::factory()->application($this->applicant)->create(['status' => GymStatus::Rejected]);
        Gym::factory()->application($this->applicant)->create(['status' => GymStatus::Returned]);

        $this->actingAs(User::factory()->create())->getJson('/api/gyms')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $gym->id)
            ->assertJsonPath('gyms.0.badge', 'Tier 1 verified')
            ->assertJsonMissingPath('gyms.0.contact_phone');
    }

    public function test_stats_count_gyms_and_show_nothing_private(): void
    {
        Gym::factory()->application($this->applicant)->create(); // draft: not registered
        $this->sent();
        $this->sent(2)->forceFill(['status' => GymStatus::UnderReview])->save();
        Gym::factory()->application($this->applicant)->create(['status' => GymStatus::Returned]);
        Gym::factory()->application($this->applicant)->create(['status' => GymStatus::Rejected]);
        Gym::factory()->application($this->applicant)->approved(1)->create(['reviewed_at' => now(), 'trial_ends_at' => now()->addDays(5)]);
        Gym::factory()->application($this->applicant)->approved(2)->create(['reviewed_at' => now()->subMonths(2), 'trial_ends_at' => now()->subDay(), 'paid_until' => now()->addMonth()]);
        Gym::factory()->application($this->applicant)->approved(2)->create(['trial_ends_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/stats')->assertOk()->assertJson([
            'gyms_registered' => 7,
            'gyms_verified' => 3,
            'verified_by_tier' => ['1' => 1, '2' => 2],
            'applications_pending' => 2,
            'applications_returned' => 1,
            'applications_rejected' => 1,
            'subscriptions' => ['trial' => 1, 'paid' => 1, 'free' => 1, 'unmanaged' => 0],
        ]);
        $this->assertCount(8, $response->json());
        $monthly = collect($response->json('monthly'))->keyBy('period');
        $this->assertCount(12, $monthly);
        $this->assertSame(['period' => now()->format('Y-m'), 'submitted' => 2, 'approved' => 1], $monthly[now()->format('Y-m')]);
        $this->assertSame(1, $monthly[now()->subMonths(2)->format('Y-m')]['approved']);
    }
}
