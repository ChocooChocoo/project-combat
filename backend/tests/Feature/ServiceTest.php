<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\GymService;
use App\Models\GymServicePhoto;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-28: services, their gallery, the price rule and publish checks. */
class ServiceTest extends TestCase
{
    use RefreshDatabase, \Tests\Feature\Concerns\GivesGymRoles;

    private Gym $gym;

    private User $owner;

    private User $employee;

    /** A real 1x1 PNG, because the "image" rule reads the file contents. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create();
        $this->employee = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function give(User $user, array $permissions, string $name = 'Test role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $role = $this->makeRole($permissions, $name);
        $role->users()->attach($user->id);
        $this->app->forgetScopedInstances();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Boxing Fundamentals',
            'discipline' => 'boxing',
            'activity_type' => 'classes',
            'overview' => 'Learn stance and footwork. Then drill the basic punches. Gloves are provided for the first class.',
            'level' => 'beginner',
            'duration_minutes' => 60,
            'frequency' => 'Every Monday',
            'included' => ['Gloves', 'Wraps'],
            'bring' => ['Water bottle'],
            'price_included' => false,
            'price' => 500,
        ], $overrides);
    }

    private function createService(array $overrides = []): GymService
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('services'), $this->payload($overrides))
            ->assertCreated()
            ->json('service.id');

        return GymService::findOrFail($id);
    }

    private function photo(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    public function test_owner_creates_a_service_and_the_index_lists_it(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('services'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('service.name', 'Boxing Fundamentals')
            ->assertJsonPath('service.price', '500.00')
            ->assertJsonPath('service.price_included', false)
            ->assertJsonPath('service.photo_urls', [])
            ->assertJsonPath('service.cover_photo_url', null)
            ->assertJsonPath('service.published_at', null);

        $this->actingAs($this->owner)->getJson($this->url('services'))
            ->assertOk()
            ->assertJsonCount(1, 'services')
            ->assertJsonPath('services.0.name', 'Boxing Fundamentals');
    }

    public function test_gym_setup_view_permission_can_list_services_but_not_change_them(): void
    {
        $this->actingAs($this->employee)->getJson($this->url('services'))->assertForbidden();

        $this->give($this->employee, ['gym_setup' => 'view'], 'Setup viewer');

        $this->actingAs($this->employee)->getJson($this->url('services'))->assertOk();
        $this->actingAs($this->employee)->postJson($this->url('services'), $this->payload())->assertForbidden();
    }

    public function test_publish_is_blocked_without_a_cover_items_and_a_two_to_four_sentence_overview(): void
    {
        $service = GymService::factory()->forGym($this->gym)->create([
            'overview' => 'This overview is long enough for the character check but it has no sentence ending punctuation',
            'included' => [],
        ]);

        $this->actingAs($this->owner)->postJson($this->url("services/{$service->id}/publish"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['overview', 'included', 'cover_photo_path'])
            ->assertJsonPath('errors.overview.0', 'Write the overview in 2 to 4 sentences.');

        $this->assertNull($service->fresh()->published_at);
    }

    public function test_publishing_exposes_the_cover_and_gallery_urls(): void
    {
        $service = $this->createService();

        $cover = $this->actingAs($this->owner)
            ->post($this->url("services/{$service->id}/cover"), ['photo' => $this->photo('cover.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('service');
        $coverPath = $service->fresh()->cover_photo_path;

        Storage::disk('public')->assertExists($coverPath);
        $this->assertSame(Storage::disk('public')->url($coverPath), $cover['cover_photo_url']);

        $gallery = $this->actingAs($this->owner)
            ->post($this->url("services/{$service->id}/photos"), ['photo' => $this->photo('gallery.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('service');

        $photo = $service->photos()->first();
        Storage::disk('public')->assertExists($photo->path);
        $this->assertSame([Storage::disk('public')->url($photo->path)], $gallery['photo_urls']);

        $this->actingAs($this->owner)->postJson($this->url("services/{$service->id}/publish"))
            ->assertOk()
            ->assertJsonPath('service.cover_photo_url', $cover['cover_photo_url']);

        $this->assertNotNull($service->fresh()->published_at);
    }

    public function test_a_sixth_gallery_photo_is_refused(): void
    {
        $service = $this->createService();

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->owner)
                ->post($this->url("services/{$service->id}/photos"), ['photo' => $this->photo("photo-{$i}.png")], ['Accept' => 'application/json'])
                ->assertOk();
        }

        $this->assertSame(5, $service->photos()->count());

        $this->actingAs($this->owner)
            ->post($this->url("services/{$service->id}/photos"), ['photo' => $this->photo('sixth.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertSame(5, $service->photos()->count());
    }

    public function test_price_is_absent_when_included_and_required_when_not(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('services'), $this->payload(['price_included' => true, 'price' => 100]))
            ->assertUnprocessable()->assertJsonValidationErrors('price');

        // price 0 is still a price, so it is not allowed alongside "included".
        $this->actingAs($this->owner)->postJson($this->url('services'), $this->payload(['price_included' => true, 'price' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('price');

        $data = $this->payload(['price_included' => false]);
        unset($data['price']);

        $this->actingAs($this->owner)->postJson($this->url('services'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('price');

        $this->actingAs($this->owner)->postJson($this->url('services'), $this->payload(['price_included' => true, 'price' => null]))
            ->assertCreated()
            ->assertJsonPath('service.price', null)
            ->assertJsonPath('service.price_included', true);

        $this->actingAs($this->owner)->postJson($this->url('services'), $this->payload(['price_included' => false, 'price' => 0]))
            ->assertCreated()
            ->assertJsonPath('service.price', '0.00');
    }

    public function test_a_published_service_cannot_be_updated_into_an_unpublishable_state(): void
    {
        $service = $this->createService();
        $this->actingAs($this->owner)
            ->post($this->url("services/{$service->id}/cover"), ['photo' => $this->photo()], ['Accept' => 'application/json'])
            ->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("services/{$service->id}/publish"))->assertOk();

        $this->actingAs($this->owner)->putJson($this->url("services/{$service->id}"), [
            'overview' => 'One long sentence that is over forty characters but does not meet the sentence count rule',
        ])->assertUnprocessable()->assertJsonValidationErrors('overview');

        $this->assertStringStartsWith('Learn stance', $service->fresh()->overview);
        $this->assertNotNull($service->fresh()->published_at);
    }

    public function test_deleting_a_service_is_blocked_by_a_plan_addon_and_removes_files_otherwise(): void
    {
        $used = $this->createService();
        $this->actingAs($this->owner)
            ->post($this->url("services/{$used->id}/cover"), ['photo' => $this->photo()], ['Accept' => 'application/json'])
            ->assertOk();

        $plan = MembershipPlan::factory()->forGym($this->gym)->create();
        $plan->addons()->create(['gym_service_id' => $used->id, 'price' => 250]);
        app(CurrentGym::class)->set($this->gym);

        $this->actingAs($this->owner)->deleteJson($this->url("services/{$used->id}"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service');

        $free = $this->createService(['name' => 'Open Mat']);
        $coverPath = $this->actingAs($this->owner)
            ->post($this->url("services/{$free->id}/cover"), ['photo' => $this->photo()], ['Accept' => 'application/json'])
            ->json('service.cover_photo_path');
        $this->actingAs($this->owner)
            ->post($this->url("services/{$free->id}/photos"), ['photo' => $this->photo()], ['Accept' => 'application/json'])
            ->assertOk();

        // The gallery payload omits the raw path, so read it from the row.
        $photoPath = $free->photos()->first()->path;
        Storage::disk('public')->assertExists($coverPath);
        Storage::disk('public')->assertExists($photoPath);

        $this->actingAs($this->owner)->deleteJson($this->url("services/{$free->id}"))
            ->assertOk()
            ->assertJsonPath('message', 'Service deleted.');

        Storage::disk('public')->assertMissing($coverPath);
        Storage::disk('public')->assertMissing($photoPath);
        $this->assertDatabaseMissing('gym_services', ['id' => $free->id]);
        $this->assertDatabaseMissing('gym_service_photos', ['gym_service_id' => $free->id]);
    }

    public function test_a_service_or_photo_from_another_gym_is_not_found(): void
    {
        $other = Gym::factory()->create();
        $foreign = GymService::factory()->forGym($other)->create();
        $foreignPhoto = GymServicePhoto::create([
            'gym_service_id' => $foreign->id,
            'path' => 'gym-services/'.$other->id.'/foreign.png',
            'sort' => 0,
        ]);
        $mine = $this->createService();
        app(CurrentGym::class)->set($this->gym);

        $this->actingAs($this->owner)->putJson($this->url("services/{$foreign->id}"), ['name' => 'Stolen'])
            ->assertNotFound();
        $this->actingAs($this->owner)->deleteJson($this->url("services/{$foreign->id}"))
            ->assertNotFound();
        $this->actingAs($this->owner)->deleteJson($this->url("services/{$mine->id}/photos/{$foreignPhoto->id}"))
            ->assertNotFound();
    }

    public function test_a_plan_manager_can_list_services_without_gym_setup_access(): void
    {
        $this->give($this->employee, ['members' => 'manage'], 'Plan manager');
        $this->createService();

        $this->actingAs($this->employee)->getJson($this->url('services'))
            ->assertOk()
            ->assertJsonCount(1, 'services');
    }

    public function test_a_staff_member_without_either_permission_cannot_list_services(): void
    {
        $this->give($this->employee, ['training' => 'view'], 'Coach only');

        $this->actingAs($this->employee)->getJson($this->url('services'))
            ->assertForbidden();
    }
}
