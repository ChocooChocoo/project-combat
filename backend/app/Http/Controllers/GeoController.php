<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** R-97: turning a typed place into map coordinates. */
class GeoController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->merge(['q' => trim((string) $request->input('q'))]);

        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $key = 'geo-search:'.mb_strtolower($data['q']);

        if (Cache::has($key)) {
            return response()->json(['results' => Cache::get($key)]);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.name').' gym discovery ('.config('app.url').')',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'jsonv2',
                'limit' => 5,
                'countrycodes' => 'ph',
                'q' => $data['q'],
            ]);
        } catch (Throwable) {
            return $this->unavailable();
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return $this->unavailable();
        }

        $results = collect($response->json())
            ->filter(fn ($result) => isset($result['lat'], $result['lon']))
            ->map(fn ($result) => [
                'label' => (string) ($result['display_name'] ?? ''),
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
            ])
            ->values()
            ->all();

        Cache::put($key, $results, now()->addDay());

        return response()->json(['results' => $results]);
    }

    /** (R-130) Coordinates to a city, province and street, for "Use my location" and the map pin. */
    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $key = 'geo-reverse:'.round((float) $data['lat'], 3).','.round((float) $data['lng'], 3);

        if (Cache::has($key)) {
            return response()->json(['place' => Cache::get($key)]);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.name').' gym discovery ('.config('app.url').')',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $data['lat'],
                'lon' => $data['lng'],
                'zoom' => 18,
                'addressdetails' => 1,
            ]);
        } catch (Throwable) {
            return $this->unavailable();
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return $this->unavailable();
        }

        $a = $response->json('address') ?? [];
        $place = [
            'city' => $a['city'] ?? $a['town'] ?? $a['municipality'] ?? null,
            'province' => $a['province'] ?? $a['state'] ?? null,
            'barangay' => $a['quarter'] ?? $a['village'] ?? $a['suburb'] ?? $a['neighbourhood'] ?? null,
            'street' => trim(($a['house_number'] ?? '').' '.($a['road'] ?? '')),
            'postcode' => $a['postcode'] ?? null,
            'label' => (string) ($response->json('display_name') ?? ''),
        ];

        Cache::put($key, $place, now()->addDay());

        return response()->json(['place' => $place]);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Address search is unavailable. Type a city instead.',
        ], 502);
    }
}
