<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-59, R-61: pay frequency, approval chain and attendance thresholds. */
class PayrollSettingsController extends Controller
{
    public function show(Gym $gym): JsonResponse
    {
        return response()->json(['settings' => $this->settings($gym)]);
    }

    public function update(Request $request, Gym $gym): JsonResponse
    {
        abort_unless($request->user()->hasGymRole($gym, GymPersonType::Owner), 403, 'Only the owner can change payroll settings.');

        $data = $request->validate([
            'frequency' => ['sometimes', 'required', Rule::in(array_keys(config('payroll.frequencies')))],
            'approval_steps' => ['sometimes', 'required', 'array', 'min:1', 'max:3'],
            'approval_steps.*' => ['required', Rule::in(array_keys(config('payroll.steps')))],
            'staff_grace_minutes' => ['sometimes', 'required', 'integer', 'between:0,240'],
            'overtime_min_minutes' => ['sometimes', 'required', 'integer', 'between:0,1440'],
        ]);

        if (array_key_exists('approval_steps', $data)) {
            $steps = array_values($data['approval_steps']);

            if (count(array_unique($steps)) !== count($steps)) {
                throw ValidationException::withMessages(['approval_steps' => 'The approval chain cannot repeat a step.']);
            }

            if (end($steps) !== 'owner') {
                throw ValidationException::withMessages(['approval_steps' => 'The owner must be the last approval step.']);
            }

            if (in_array('owner', array_slice($steps, 0, -1), true)) {
                throw ValidationException::withMessages(['approval_steps' => 'The owner can only be the last approval step.']);
            }

            $data['payroll_steps'] = $steps;
            unset($data['approval_steps']);
        }

        if (array_key_exists('frequency', $data)) {
            $data['payroll_frequency'] = $data['frequency'];
            unset($data['frequency']);
        }

        $gym->update($data);

        return response()->json(['settings' => $this->settings($gym->fresh())]);
    }

    /** @return array<string, mixed> */
    private function settings(Gym $gym): array
    {
        return [
            'frequency' => $gym->payroll_frequency ?: 'semi_monthly',
            'approval_steps' => $gym->payrollSteps(),
            'staff_grace_minutes' => (int) $gym->staff_grace_minutes,
            'overtime_min_minutes' => (int) $gym->overtime_min_minutes,
        ];
    }
}
