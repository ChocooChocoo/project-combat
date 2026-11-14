<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** R-97: typed address search through OpenStreetMap Nominatim. */
class GeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
    }

    public function test_search_returns_simplified_results_and_caches_them(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Makati, Metro Manila, Philippines',
                    'lat' => '14.5547',
                    'lon' => '121.0244',
                    'place_id' => 1,
                ],
                [
                    'display_name' => 'Cebu City, Cebu, Philippines',
                    'lat' => '10.3157',
                    'lon' => '123.8854',
                ],
            ]),
        ]);

        $this->getJson('/api/geo/search?q=Makati')->assertOk()->assertExactJson([
            'results' => [
                ['label' => 'Makati, Metro Manila, Philippines', 'lat' => 14.5547, 'lng' => 121.0244],
                ['label' => 'Cebu City, Cebu, Philippines', 'lat' => 10.3157, 'lng' => 123.8854],
            ],
        ]);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), 'nominatim.openstreetmap.org/search')
                && $query['format'] === 'jsonv2'
                && $query['limit'] === '5'
                && $query['countrycodes'] === 'ph'
                && $query['q'] === 'Makati'
                && str_contains(implode(' ', $request->header('User-Agent')), config('app.name'));
        });

        // The lowercased query uses the same 24-hour cache entry.
        $this->getJson('/api/geo/search?q=makati')->assertOk()->assertJsonCount(2, 'results');
        Http::assertSentCount(1);
    }

    public function test_upstream_failure_returns_a_502_message(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response('Service unavailable', 503),
        ]);

        $this->getJson('/api/geo/search?q=Cebu')->assertStatus(502)->assertExactJson([
            'message' => 'Address search is unavailable. Type a city instead.',
        ]);
    }

    public function test_a_query_shorter_than_two_characters_is_refused_without_calling_nominatim(): void
    {
        $this->getJson('/api/geo/search?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
        Http::assertNothingSent();
    }

    public function test_reverse_maps_an_address_and_is_public(): void
    {
        Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response([
            'display_name' => 'x',
            'address' => ['town' => 'Silang', 'province' => 'Cavite', 'village' => 'Biga'],
        ])]);
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/geo/reverse?lat=14.2&lng=120.97')->assertOk()
            ->assertJsonPath('place.city', 'Silang')
            ->assertJsonPath('place.province', 'Cavite')
            ->assertJsonPath('place.barangay', 'Biga');
    }

    public function test_reverse_refuses_bad_coordinates_and_reports_failures(): void
    {
        $this->getJson('/api/geo/reverse?lat=200&lng=120')->assertUnprocessable()->assertJsonValidationErrors('lat');

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('x', 500)]);
        $this->getJson('/api/geo/reverse?lat=14.2&lng=120.97')->assertStatus(502);
    }
}
