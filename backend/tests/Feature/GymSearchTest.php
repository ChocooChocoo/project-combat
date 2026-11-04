<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymService;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-30 and R-97: search, filters and distance ordering for approved gyms. */
class GymSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function gym(array $attributes = []): Gym
    {
        return Gym::factory()->approved(1)->create(array_merge([
            'name' => 'Test Gym',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'disciplines' => ['boxing'],
            'activity_types' => ['classes'],
            'operating_hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']],
        ], $attributes));
    }

    private function service(Gym $gym, string $name, bool $published = true): GymService
    {
        app(CurrentGym::class)->set($gym);

        return GymService::factory()->forGym($gym)->create([
            'name' => $name,
            'published_at' => $published ? now() : null,
        ]);
    }

    public function test_the_text_city_and_province_filters_work(): void
    {
        $makati = $this->gym(['name' => 'Makati Boxing Gym', 'city' => 'Makati', 'province' => 'Metro Manila']);
        $cebu = $this->gym(['name' => 'Cebu Fight Club', 'city' => 'Cebu City', 'province' => 'Cebu']);
        $this->gym(['name' => 'Davao Grappling', 'city' => 'Davao City', 'province' => 'Davao del Sur']);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms?q=Makati')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $makati->id)
            ->assertJsonPath('total', 1);

        $this->getJson('/api/gyms?q=Cebu')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $cebu->id);

        $this->getJson('/api/gyms?q=Zamboanga')->assertOk()
            ->assertJsonCount(0, 'gyms')
            ->assertJsonPath('total', 0);

        $this->getJson('/api/gyms?city=Makati')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $makati->id);

        $this->getJson('/api/gyms?province=Cebu')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $cebu->id);
    }

    public function test_discipline_service_tier_and_day_filters_work(): void
    {
        $boxing = $this->gym([
            'name' => 'Boxing Gym',
            'disciplines' => ['boxing'],
            'operating_hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']],
        ]);
        $muayThai = $this->gym([
            'name' => 'Muay Thai Gym',
            'disciplines' => ['muay_thai'],
            'operating_hours' => ['tue' => ['open' => '08:00', 'close' => '20:00']],
        ]);
        Gym::whereKey($muayThai->id)->update(['approved_tier' => 2]);

        $bjj = $this->gym([
            'name' => 'BJJ Gym',
            'disciplines' => ['bjj'],
            'operating_hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']],
        ]);
        Gym::whereKey($bjj->id)->update(['approved_tier' => 2]);

        $this->service($boxing, 'Boxing Fundamentals');
        $this->service($muayThai, 'Muay Thai Intro');
        $this->service($bjj, 'BJJ Open Mat', published: false);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms?discipline=muay_thai')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $muayThai->id);

        $this->getJson('/api/gyms?service=Boxing')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $boxing->id);

        $this->getJson('/api/gyms?service=Open+Mat')->assertOk()
            ->assertJsonCount(0, 'gyms');

        $this->getJson('/api/gyms?tier=2')->assertOk()
            ->assertJsonCount(2, 'gyms');

        $this->getJson('/api/gyms?tier=1')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $boxing->id);

        $this->getJson('/api/gyms?day=mon')->assertOk()
            ->assertJsonCount(2, 'gyms');

        $this->getJson('/api/gyms?day=tue')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('gyms.0.id', $muayThai->id);
    }

    public function test_distance_sort_uses_stored_coordinates_and_puts_missing_coordinates_last(): void
    {
        $near = $this->gym(['name' => 'Near Gym', 'latitude' => 14.5547, 'longitude' => 121.0244]);
        $far = $this->gym(['name' => 'Far Gym', 'latitude' => 14.6547, 'longitude' => 121.0244]);
        $none = $this->gym(['name' => 'No Location Gym', 'latitude' => null, 'longitude' => null]);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms?lat=14.5547&lng=121.0244&sort=distance&distance_km=999')->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('gyms.0.id', $near->id)
            ->assertJsonPath('gyms.0.distance_km', 0)
            ->assertJsonPath('gyms.1.id', $far->id)
            ->assertJsonPath('gyms.1.distance_km', 11.1)
            ->assertJsonPath('gyms.2.id', $none->id)
            ->assertJsonPath('gyms.2.distance_km', null);

        $this->getJson('/api/gyms?sort=name')->assertOk()
            ->assertJsonPath('gyms.0.name', 'Far Gym')
            ->assertJsonPath('gyms.1.name', 'Near Gym')
            ->assertJsonPath('gyms.2.name', 'No Location Gym')
            ->assertJsonPath('gyms.0.distance_km', null);
    }

    public function test_invalid_search_parameters_are_refused(): void
    {
        $this->gym();
        $this->app->forgetScopedInstances();

        foreach ([
            ['lat' => 91],
            ['lng' => 181],
            ['sort' => 'price'],
            ['tier' => 3],
            ['tier' => 0],
            ['day' => 'funday'],
        ] as $parameters) {
            $this->getJson('/api/gyms?'.http_build_query($parameters))
                ->assertUnprocessable()
                ->assertJsonValidationErrors([array_key_first($parameters)]);
        }
    }
}
