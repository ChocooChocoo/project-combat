<?php

namespace Tests\Feature;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-131: platform admins are told when an application is sent. */
class AdminApplicationNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->applicant = User::factory()->owner()->create();
    }

    private function admins(): array
    {
        return [1, 2] ? array_map(fn () => tap(User::factory()->create())->forceFill(['is_platform_admin' => true])->save() ? User::latest('id')->first() : null, [1, 2]) : [];
    }

    private function submit(Gym $gym)
    {
        foreach (['dti', 'mayors_permit', 'barangay_clearance'] as $type) {
            $this->actingAs($this->applicant)->post("/api/gym-applications/{$gym->id}/documents", [
                'type' => $type,
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                'expires_on' => today()->addYear()->toDateString(),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        return $this->actingAs($this->applicant)->postJson("/api/gym-applications/{$gym->id}/submit");
    }

    public function test_every_admin_gets_one_notice_when_an_application_is_sent(): void
    {
        $admins = $this->admins();
        $plainOwner = User::factory()->owner()->create();
        $gym = Gym::factory()->application($this->applicant)->create();

        $this->submit($gym)->assertOk();

        foreach ($admins as $admin) {
            $this->assertCount(1, $admin->notifications);
            $this->assertSame("/admin/applications/{$gym->id}", $admin->notifications->first()->data['url']);
            $this->assertSame('New gym application', $admin->notifications->first()->data['title']);
        }
        $this->assertCount(0, $this->applicant->notifications);
        $this->assertCount(0, $plainOwner->notifications);
    }

    public function test_a_returned_application_sent_again_says_so(): void
    {
        $admin = $this->admins()[0];
        $gym = Gym::factory()->application($this->applicant)->create(['status' => GymStatus::Returned]);

        $this->submit($gym)->assertOk();

        $this->assertSame('Gym application sent again', $admin->notifications->first()->data['title']);
    }
}
