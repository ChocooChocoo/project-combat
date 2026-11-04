<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymPreference;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-98: preference-driven gym recommendations with visible reasons. */
class RecommendationTest extends TestCase
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
            'latitude' => null,
            'longitude' => null,
            'disciplines' => ['boxing'],
        ], $attributes));
    }

    private function plan(Gym $gym, float $fee): MembershipPlan
    {
        app(CurrentGym::class)->set($gym);

        return MembershipPlan::factory()->forGym($gym)->create([
            'total_fee' => $fee,
            'published_at' => now(),
        ]);
    }

    public function test_discipline_price_and_distance_are_scored_with_reasons(): void
    {
        GymPreference::factory()->create([
            'user_id' => auth()->id(),
            'discipline' => 'muay_thai',
            'min_price' => 1000,
            'max_price' => 2000,
            'preferred_times' => ['evening'],
        ]);

        $match = $this->gym([
            'name' => 'Match Gym',
            'disciplines' => ['muay_thai'],
            'latitude' => 14.5547,
            'longitude' => 121.0244,
        ]);
        $this->plan($match, 1500);

        $twoKm = $this->gym([
            'name' => 'Two Km Gym',
            'disciplines' => ['boxing'],
            'latitude' => 14.5735867,
            'longitude' => 121.0244,
        ]);
        $this->plan($twoKm, 5000);

        $far = $this->gym([
            'name' => 'Far Gym',
            'disciplines' => ['boxing'],
            'latitude' => 15.5547,
            'longitude' => 121.0244,
        ]);
        $this->plan($far, 5000);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms/recommendations?lat=14.5547&lng=121.0244')->assertOk()
            ->assertJsonCount(3, 'recommendations')
            ->assertJsonPath('recommendations.0.gym.id', $match->id)
            ->assertJsonPath('recommendations.0.score', 100)
            ->assertJsonPath('recommendations.0.reasons', [
                '0.0 km away',
                'Teaches Muay Thai',
                'Plan from ₱1,500',
            ])
            ->assertJsonPath('recommendations.0.gym.distance_km', 0)
            ->assertJsonPath('recommendations.0.gym.min_price', '1500.00')
            ->assertJsonPath('recommendations.1.gym.id', $twoKm->id)
            ->assertJsonPath('recommendations.1.score', 28.74)
            ->assertJsonPath('recommendations.1.reasons', ['2.1 km away'])
            ->assertJsonPath('recommendations.2.gym.id', $far->id)
            ->assertJsonPath('recommendations.2.score', 0)
            ->assertJsonPath('recommendations.2.reasons', ['Approved gym'])
            ->assertJsonPath('preferences.discipline', 'muay_thai')
            ->assertJsonPath('preferences.preferred_times', ['evening']);
    }

    public function test_preferences_are_remembered_and_used_for_the_next_ranking(): void
    {
        $this->getJson('/api/gym-preferences')->assertOk()->assertExactJson([
            'preferences' => [
                'discipline' => null,
                'min_price' => null,
                'max_price' => null,
                'preferred_times' => [],
            ],
        ]);

        $this->putJson('/api/gym-preferences', [
            'discipline' => 'boxing',
            'min_price' => 1000,
            'max_price' => 2500,
            'preferred_times' => ['morning', 'evening'],
        ])->assertOk()
            ->assertJsonPath('preferences.discipline', 'boxing')
            ->assertJsonPath('preferences.min_price', '1000.00')
            ->assertJsonPath('preferences.max_price', '2500.00')
            ->assertJsonPath('preferences.preferred_times', ['morning', 'evening']);

        $this->putJson('/api/gym-preferences', ['min_price' => 3000, 'max_price' => 2000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_price');

        $this->getJson('/api/gym-preferences')->assertOk()
            ->assertJsonPath('preferences.discipline', 'boxing')
            ->assertJsonPath('preferences.min_price', '1000.00')
            ->assertJsonPath('preferences.max_price', '2500.00')
            ->assertJsonPath('preferences.preferred_times', ['morning', 'evening']);

        $gym = $this->gym(['name' => 'Boxing Gym', 'disciplines' => ['boxing']]);
        $this->plan($gym, 1500);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms/recommendations')->assertOk()
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.gym.id', $gym->id)
            ->assertJsonPath('recommendations.0.score', 100)
            ->assertJsonPath('recommendations.0.reasons', [
                'Teaches Boxing',
                'Plan from ₱1,500',
            ])
            ->assertJsonPath('preferences.discipline', 'boxing');
    }

    public function test_weights_are_normalised_when_distance_and_price_are_missing(): void
    {
        $preference = GymPreference::factory()->create([
            'user_id' => auth()->id(),
            'discipline' => 'boxing',
            'min_price' => null,
            'max_price' => null,
            'preferred_times' => [],
        ]);

        $boxing = $this->gym(['name' => 'Boxing Gym', 'disciplines' => ['boxing']]);
        $muayThai = $this->gym(['name' => 'Muay Thai Gym', 'disciplines' => ['muay_thai']]);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms/recommendations')->assertOk()
            ->assertJsonPath('recommendations.0.gym.id', $boxing->id)
            ->assertJsonPath('recommendations.0.score', 100)
            ->assertJsonPath('recommendations.0.reasons', ['Teaches Boxing'])
            ->assertJsonPath('recommendations.1.gym.id', $muayThai->id)
            ->assertJsonPath('recommendations.1.score', 0)
            ->assertJsonPath('recommendations.1.reasons', ['Approved gym']);

        $preference->update(['min_price' => 1000, 'max_price' => 2000]);

        $this->plan($boxing, 1500);
        $this->plan($muayThai, 1200);
        $expensive = $this->gym(['name' => 'Expensive Boxing', 'disciplines' => ['boxing']]);
        $this->plan($expensive, 5000);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms/recommendations')->assertOk()
            ->assertJsonPath('recommendations.0.gym.id', $boxing->id)
            ->assertJsonPath('recommendations.0.score', 100)
            ->assertJsonPath('recommendations.1.gym.id', $expensive->id)
            ->assertJsonPath('recommendations.1.score', 57.14)
            ->assertJsonPath('recommendations.2.gym.id', $muayThai->id)
            ->assertJsonPath('recommendations.2.score', 42.86)
            ->assertJsonPath('recommendations.2.reasons', ['Plan from ₱1,200']);
    }

    public function test_minimum_or_maximum_price_alone_is_enough(): void
    {
        $preference = GymPreference::factory()->create([
            'user_id' => auth()->id(),
            'discipline' => null,
            'min_price' => 1000,
            'max_price' => null,
            'preferred_times' => [],
        ]);

        $cheap = $this->gym(['name' => 'Cheap Gym']);
        $this->plan($cheap, 900);
        $aboveMinimum = $this->gym(['name' => 'Above Minimum']);
        $this->plan($aboveMinimum, 1500);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms/recommendations')->assertOk()
            ->assertJsonPath('recommendations.0.gym.id', $aboveMinimum->id)
            ->assertJsonPath('recommendations.0.score', 100)
            ->assertJsonPath('recommendations.1.gym.id', $cheap->id)
            ->assertJsonPath('recommendations.1.score', 0);

        $preference->update(['min_price' => null, 'max_price' => 2000]);
        $aboveMaximum = $this->gym(['name' => 'Above Maximum']);
        $this->plan($aboveMaximum, 2500);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms/recommendations')->assertOk()
            ->assertJsonPath('recommendations.0.gym.id', $aboveMinimum->id)
            ->assertJsonPath('recommendations.0.score', 100)
            ->assertJsonPath('recommendations.1.gym.id', $cheap->id)
            ->assertJsonPath('recommendations.1.score', 100)
            ->assertJsonPath('recommendations.2.gym.id', $aboveMaximum->id)
            ->assertJsonPath('recommendations.2.score', 0);
    }

    public function test_the_configured_limit_caps_the_ranking(): void
    {
        config(['discovery.limit' => 2]);

        $this->gym(['name' => 'Alpha Gym']);
        $this->gym(['name' => 'Beta Gym']);
        $this->gym(['name' => 'Gamma Gym']);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms/recommendations')->assertOk()
            ->assertJsonCount(2, 'recommendations')
            ->assertJsonPath('recommendations.0.gym.name', 'Alpha Gym')
            ->assertJsonPath('recommendations.1.gym.name', 'Beta Gym')
            ->assertJsonPath('recommendations.0.reasons', ['Approved gym']);
    }
}
