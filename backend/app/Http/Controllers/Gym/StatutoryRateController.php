<?php

namespace App\Http\Controllers\Gym;

use App\Enums\StatutoryKind;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\StatutoryRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-60: view effective-dated statutory tables and add a new one. */
class StatutoryRateController extends Controller
{
    public function index(Gym $gym): JsonResponse
    {
        $rates = StatutoryRate::query()
            ->forGym($gym)
            ->with('createdBy')
            ->orderBy('kind')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StatutoryRate $rate) => $this->ratePayload($rate))
            ->values();

        return response()->json(['rates' => $rates]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(StatutoryKind::class)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'source' => ['nullable', 'string', 'max:255'],
            'brackets' => ['required', 'array', 'min:1'],
            'brackets.*' => ['required', 'array'],
            'brackets.*.min' => ['required', 'numeric', 'min:0'],
            'brackets.*.max' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.employee' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.employer' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.ec' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.employee_rate' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.employer_rate' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.base' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.rate' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($data['brackets'] as $index => $bracket) {
            if (isset($bracket['max']) && (float) $bracket['max'] < (float) $bracket['min']) {
                throw ValidationException::withMessages([
                    "brackets.{$index}.max" => 'A bracket cannot end before it starts.',
                ]);
            }

            $hasShare = collect(['employee', 'employer', 'employee_rate', 'employer_rate', 'base', 'rate', 'tax'])
                ->contains(fn (string $key) => array_key_exists($key, $bracket) && $bracket[$key] !== null);

            if (! $hasShare) {
                throw ValidationException::withMessages([
                    "brackets.{$index}" => 'Each bracket needs a fixed amount or a rate.',
                ]);
            }
        }

        $rate = StatutoryRate::create([
            'gym_id' => $gym->id,
            'kind' => $data['kind'],
            'effective_from' => $data['effective_from'],
            'brackets' => $data['brackets'],
            'source' => $data['source'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $rate->load('createdBy');

        return response()->json(['rate' => $this->ratePayload($rate)], 201);
    }

    private function ratePayload(StatutoryRate $rate): array
    {
        return [
            'id' => $rate->id,
            'gym_id' => $rate->gym_id,
            'kind' => $rate->kind,
            'effective_from' => $rate->effective_from->toDateString(),
            'source' => $rate->source,
            'brackets' => $rate->brackets,
            'created_at' => $rate->created_at?->toIso8601String(),
            'created_by_name' => $rate->createdBy
                ? trim("{$rate->createdBy->first_name} {$rate->createdBy->last_name}")
                : null,
        ];
    }
}
