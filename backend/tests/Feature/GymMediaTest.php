<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-134, R-135: gym cover photo, gallery and policy PDFs. */
class GymMediaTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
    }

    private function addFile(string $kind, UploadedFile $file)
    {
        return $this->actingAs($this->owner)
            ->postJson($this->url('media/files'), ['kind' => $kind, 'file' => $file]);
    }

    public function test_the_owner_sets_a_cover_and_adds_photos_and_a_policy(): void
    {
        $this->actingAs($this->owner)
            ->postJson($this->url('media/cover'), ['photo' => UploadedFile::fake()->create('cover.jpg', 50, 'image/jpeg')])
            ->assertOk();
        $this->addFile('photo', UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg'))->assertOk();
        $this->addFile('photo', UploadedFile::fake()->create('b.jpg', 50, 'image/jpeg'))->assertOk();
        $this->addFile('policy', UploadedFile::fake()->create('rules.pdf', 50, 'application/pdf'))->assertOk();

        $this->actingAs($this->owner)->getJson($this->url('setup'))
            ->assertOk()
            ->assertJsonCount(2, 'setup.media.photos')
            ->assertJsonCount(1, 'setup.media.policy_files')
            ->assertJsonPath('setup.media.policy_files.0.name', 'rules.pdf')
            ->assertJsonPath('setup.options.gym_photos.max', 12);

        $this->assertNotNull($this->gym->fresh()->cover_photo_path);
        $this->assertCount(4, Storage::disk('public')->allFiles());
    }

    public function test_limits_and_file_types_are_enforced(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->addFile('photo', UploadedFile::fake()->create("p{$i}.jpg", 50, 'image/jpeg'))->assertOk();
        }

        $this->addFile('photo', UploadedFile::fake()->create('p13.jpg', 50, 'image/jpeg'))->assertUnprocessable();
        $this->addFile('policy', UploadedFile::fake()->create('rules.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
            ->assertUnprocessable();
    }

    public function test_only_settings_managers_can_change_media(): void
    {
        $clerk = $this->employeeWith(['members' => 'view']);

        $this->actingAs($clerk)
            ->postJson($this->url('media/files'), ['kind' => 'photo', 'file' => UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg')])
            ->assertForbidden();
        $this->actingAs($clerk)
            ->postJson($this->url('media/cover'), ['photo' => UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg')])
            ->assertForbidden();
    }

    public function test_deleting_removes_the_file_and_other_gyms_files_are_not_found(): void
    {
        $this->addFile('photo', UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg'))->assertOk();
        $file = GymFile::withoutGlobalScope('gym')->first();
        Storage::disk('public')->assertExists($file->path);

        $other = Gym::factory()->approved()->create();
        $foreignId = DB::table('gym_files')->insertGetId([
            'gym_id' => $other->id, 'kind' => 'photo', 'path' => 'gyms/x/y.jpg', 'original_name' => 'y.jpg', 'size' => 1,
        ]);

        $this->actingAs($this->owner)->deleteJson($this->url("media/files/{$foreignId}"))->assertNotFound();

        $this->actingAs($this->owner)->deleteJson($this->url("media/files/{$file->id}"))->assertOk();
        Storage::disk('public')->assertMissing($file->path);
        $this->assertSame(0, GymFile::withoutGlobalScope('gym')->where('gym_id', $this->gym->id)->count());
    }

    public function test_public_pages_show_the_media(): void
    {
        $this->actingAs($this->owner)
            ->postJson($this->url('media/cover'), ['photo' => UploadedFile::fake()->create('cover.jpg', 50, 'image/jpeg')])
            ->assertOk();
        $this->addFile('photo', UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg'))->assertOk();
        $this->addFile('policy', UploadedFile::fake()->create('rules.pdf', 50, 'application/pdf'))->assertOk();

        $this->actingAs($this->applicant)->getJson("/api/gyms/{$this->gym->id}")
            ->assertOk()
            ->assertJsonPath('gym.needs_terms', true)
            ->assertJsonCount(1, 'gym.photos')
            ->assertJsonCount(1, 'gym.policy_files')
            ->assertJsonPath('gym.policy_files.0.name', 'rules.pdf')
            ->assertJsonStructure(['gym' => ['cover_photo_url']]);

        $this->actingAs($this->applicant)->getJson('/api/gyms')
            ->assertOk()
            ->assertJsonPath('gyms.0.cover_photo_url', fn ($url) => is_string($url) && $url !== '');
    }
}
