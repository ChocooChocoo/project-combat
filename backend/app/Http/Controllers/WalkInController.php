<?php

namespace App\Http\Controllers;

use App\Enums\CustomerSource;
use App\Enums\GymStatus;
use App\Http\Controllers\Concerns\PresentsGymMedia;
use App\Models\Customer;
use App\Models\Gym;
use App\Models\WalkIn;
use App\Support\CurrentGym;
use App\Support\CustomerLinker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-137: the public walk-in kiosk. No sign-in, so it returns no ids or visitor data. */
class WalkInController extends Controller
{
    use PresentsGymMedia;

    public function form(int $gym): JsonResponse
    {
        $gym = Gym::where('status', GymStatus::Approved->value)->findOrFail($gym);
        $files = $this->gymMedia($gym)['policy_files'];

        return response()->json([
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'policies' => $gym->policies,
                'policy_files' => $files,
                'needs_terms' => filled($gym->policies) || $files !== [],
            ],
            'health_questions' => config('membership.health_questions'),
            'waivers' => config('membership.waivers'),
        ]);
    }

    public function store(Request $request, int $gym, CustomerLinker $linker): JsonResponse
    {
        $gym = Gym::where('status', GymStatus::Approved->value)->findOrFail($gym);
        app(CurrentGym::class)->set($gym);

        $needsTerms = filled($gym->policies) || $this->gymMedia($gym)['policy_files'] !== [];
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s()-]{7,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'health_answers' => ['nullable', 'array:'.implode(',', array_keys(config('membership.health_questions')))],
            'health_notes' => ['nullable', 'string', 'max:2000'],
            'waivers' => ['required', 'array'],
            'signed_name' => ['required', 'string', 'max:150'],
        ];

        foreach (array_keys(config('membership.health_questions')) as $key) {
            $rules["health_answers.{$key}"] = ['nullable', 'boolean'];
        }

        foreach (array_keys(config('membership.waivers')) as $key) {
            $rules["waivers.{$key}"] = ['accepted'];
        }

        if ($needsTerms) {
            $rules['waivers.gym_terms'] = ['accepted'];
        }

        $data = $request->validate($rules, [
            'waivers.*.accepted' => 'Accept this waiver to continue.',
            'phone.regex' => 'Enter a valid phone number, for example 0917 123 4567.',
        ]);

        $email = filled($data['email'] ?? null) ? $data['email'] : null;
        $customer = $linker->customerForEmail($gym, $email)
            ?? Customer::where('phone', $data['phone'])->first()
            ?? Customer::create([
                'name' => $data['name'], 'email' => $email, 'phone' => $data['phone'],
                'source' => CustomerSource::WalkIn, 'inquiry' => 'Walk-in visit',
            ]);

        WalkIn::create([
            'customer_id' => $customer->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $email,
            'health_answers' => array_map(fn ($answer) => filter_var($answer, FILTER_VALIDATE_BOOLEAN), $data['health_answers'] ?? []),
            'health_notes' => $data['health_notes'] ?? null,
            'waivers' => [...array_keys(config('membership.waivers')), ...($needsTerms ? ['gym_terms'] : [])],
            'signed_name' => $data['signed_name'],
            'checked_in_at' => now(),
        ]);

        return response()->json(['message' => 'Thank you. Please pay at the front desk.'], 201);
    }
}
