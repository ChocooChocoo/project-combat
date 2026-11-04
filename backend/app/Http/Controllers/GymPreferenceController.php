<?php

namespace App\Http\Controllers;

use App\Models\GymPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-98: the sport, price range and times one person prefers. */
class GymPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $this->payload($request->user()->gymPreference()->first()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'discipline' => ['nullable', Rule::in(array_keys(config('verification.disciplines')))],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'preferred_times' => ['nullable', 'array'],
            'preferred_times.*' => ['string', Rule::in(array_keys(config('discovery.preferred_times')))],
        ]);

        $current = $request->user()->gymPreference()->first();
        $min = array_key_exists('min_price', $data) ? $data['min_price'] : $current?->min_price;
        $max = array_key_exists('max_price', $data) ? $data['max_price'] : $current?->max_price;

        if ($min !== null && $max !== null && (float) $max < (float) $min) {
            throw ValidationException::withMessages([
                'max_price' => 'The maximum price must be at least the minimum price.',
            ]);
        }

        $preference = $request->user()->gymPreference()->updateOrCreate([], $data);

        return response()->json(['preferences' => $this->payload($preference)]);
    }

    private function payload(?GymPreference $preference): array
    {
        return [
            'discipline' => $preference?->discipline,
            'min_price' => $preference?->min_price,
            'max_price' => $preference?->max_price,
            'preferred_times' => $preference?->preferred_times ?? [],
        ];
    }
}
