<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-20: the gym's workspace settings. */
class GymSetupController extends Controller
{
    /** Plain-language messages for the fields whose default wording shows raw field names. */
    private const MESSAGES = [
        'operating_hours.array' => 'Operating hours can only be set for Monday to Sunday.',
        'operating_hours.*' => 'Set an opening and closing time, or leave the day closed.',
        'operating_hours.*.open.date_format' => 'Use a time such as 08:00.',
        'operating_hours.*.close.date_format' => 'Use a time such as 20:00.',
        'operating_hours.*.close.after' => 'Closing time must be after opening time.',
        'disciplines.*.in' => 'Choose combat sports from the list.',
        'activity_types.*.in' => 'Choose activity types from the list.',
        'working_days.*.in' => 'Choose working days from Monday to Sunday.',
    ];

    public function show(Gym $gym): JsonResponse
    {
        return response()->json(['setup' => $this->setup($gym)]);
    }

    public function update(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate($this->rules(), self::MESSAGES);

        if (array_key_exists('operating_hours', $data) && is_array($data['operating_hours'])) {
            $data['operating_hours'] = collect($data['operating_hours'])
                ->map(fn ($day) => is_array($day) && filled($day['open'] ?? null) && filled($day['close'] ?? null)
                    ? ['open' => $day['open'], 'close' => $day['close']]
                    : null)
                ->all();
        }

        // R-61: the owner is always the last step, whatever order was sent.
        if (array_key_exists('payroll_steps', $data)) {
            $steps = array_values(array_unique(array_diff($data['payroll_steps'] ?? [], ['owner'])));
            $data['payroll_steps'] = [...array_slice($steps, 0, 2), 'owner'];
        }

        $gym->update($data);

        return response()->json(['setup' => $this->setup($gym->fresh())]);
    }

    public function options(Gym $gym): JsonResponse
    {
        return response()->json(['options' => $this->optionLists($gym)]);
    }

    private function setup(Gym $gym): array
    {
        return [
            'id' => $gym->id,
            'name' => $gym->name,
            'status' => $gym->status?->value,
            'tier' => $gym->tier,
            'approved_tier' => $gym->approved_tier,
            'badge' => $gym->approved_tier
                ? config("verification.tiers.{$gym->approved_tier}.badge")
                : null,
            'profile' => $gym->only([
                'address_line', 'barangay', 'city', 'province', 'latitude', 'longitude',
                'contact_phone', 'contact_email',
            ]),
            'disciplines' => $gym->disciplines ?? [],
            'activity_types' => $gym->activity_types ?? [],
            'operating_hours' => $gym->operating_hours ?? [],
            'working_days' => $gym->workingDays(),
            'policies' => $gym->policies,
            'booking_approval' => $gym->booking_approval,
            'late_grace_minutes' => $gym->late_grace_minutes,
            'cancel_cutoff_hours' => $gym->cancel_cutoff_hours,
            'session_reminder_minutes' => $gym->session_reminder_minutes,
            'payroll_frequency' => $gym->payroll_frequency,
            'payroll_steps' => $gym->payrollSteps(),
            'staff_grace_minutes' => $gym->staff_grace_minutes,
            'overtime_min_minutes' => $gym->overtime_min_minutes,
            'services' => $gym->services ?? [],
            'facilities' => $gym->facilities ?? [],
            'options' => $this->optionLists($gym),
        ];
    }

    private function optionLists(Gym $gym): array
    {
        return [
            'disciplines' => config('verification.disciplines'),
            'activity_types' => config('offer.activity_types'),
            'weekdays' => config('hr.weekdays'),
            'working_days' => $gym->workingDays(),
            'default_working_days' => Gym::DEFAULT_WORKING_DAYS,
            'pay_bases' => config('hr.pay_bases'),
            'employment_types' => config('hr.employment_types'),
            'leave_types' => config('hr.leave_types'),
            'attachment' => config('hr.attachment'),
            'payroll_frequencies' => config('payroll.frequencies'),
            'payroll_steps' => config('payroll.steps'),
            'levels' => config('offer.levels'),
            'frequencies' => config('offer.frequencies'),
            'plan_billing' => config('offer.plan_billing'),
            'service_photos' => config('offer.service_photos'),
        ];
    }

    private function rules(): array
    {
        $days = 'mon,tue,wed,thu,fri,sat,sun';

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'disciplines' => ['sometimes', 'nullable', 'array'],
            'disciplines.*' => ['distinct', Rule::in(array_keys(config('verification.disciplines')))],
            'activity_types' => ['sometimes', 'nullable', 'array'],
            'activity_types.*' => ['distinct', Rule::in(array_keys(config('offer.activity_types')))],
            'operating_hours' => ['sometimes', 'nullable', "array:{$days}"],
            'operating_hours.*' => ['nullable', 'array:open,close'],
            'operating_hours.*.open' => ['nullable', 'required_with:operating_hours.*.close', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'required_with:operating_hours.*.open', 'date_format:H:i', 'after:operating_hours.*.open'],
            'working_days' => ['sometimes', 'nullable', 'array'],
            'working_days.*' => ['distinct', Rule::in(array_keys(config('hr.weekdays')))],
            'policies' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'booking_approval' => ['sometimes', 'required', 'boolean'],
            'late_grace_minutes' => ['sometimes', 'required', 'integer', 'between:0,120'],
            'cancel_cutoff_hours' => ['sometimes', 'required', 'integer', 'between:0,72'],
            'session_reminder_minutes' => ['sometimes', 'required', 'integer', Rule::in([30, 60, 120, 1440])],
            'payroll_frequency' => ['sometimes', 'required', Rule::in(array_keys(config('payroll.frequencies')))],
            'payroll_steps' => ['sometimes', 'array', 'max:3'],
            'payroll_steps.*' => ['distinct', Rule::in(array_keys(config('payroll.steps')))],
            'staff_grace_minutes' => ['sometimes', 'required', 'integer', 'between:0,120'],
            'overtime_min_minutes' => ['sometimes', 'required', 'integer', 'between:0,240'],
        ];
    }
}
