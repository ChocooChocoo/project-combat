<?php

namespace App\Http\Controllers;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Concerns\PresentsGymMedia;
use App\Http\Controllers\Concerns\PresentsMemberships;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Notifications\MembershipApplicationSubmitted;
use App\Support\CurrentGym;
use App\Support\CustomerLinker;
use App\Support\MembershipLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * R-31, R-32: a platform user applies to join a gym. This sits outside the gym
 * check because the applicant does not belong to the gym yet.
 */
class MembershipApplicationController extends Controller
{
    use PresentsGymMedia, PresentsMemberships;

    public function form(): JsonResponse
    {
        return response()->json([
            'health_questions' => config('membership.health_questions'),
            'waivers' => config('membership.waivers'),
            'emergency_contacts_max' => config('membership.emergency_contacts_max'),
            'medical_file' => config('membership.medical_file'),
        ]);
    }

    public function store(Request $request, int $gym, CustomerLinker $linker): JsonResponse
    {
        $user = $request->user();
        $gym = Gym::where('status', GymStatus::Approved->value)->findOrFail($gym);

        abort_if($user->hasGymRole($gym, GymPersonType::Owner), 422, 'You own this gym.');

        // This gym's records only, on purpose: the person is not inside the gym yet.
        app(CurrentGym::class)->set($gym);

        $data = $request->validate($this->rules($gym), [
            'plan_id.exists' => 'Choose one of this gym\'s plans.',
            'waivers.*.accepted' => 'Accept this waiver to continue.',
            'waivers.gym_terms.accepted' => 'Accept the gym\'s policies and waiver to continue.',
            'medical_file.required' => 'Upload your medical clearance.',
            'emergency_contacts.required' => 'Add at least one emergency contact.',
            'emergency_contacts.min' => 'Add at least one emergency contact.',
            'emergency_contacts.*.phone.regex' => 'Enter a valid phone number, for example 0917 123 4567.',
        ]);

        if ($gym->memberships()->where('user_id', $user->id)->whereIn('status', MembershipStatus::live())->exists()) {
            throw ValidationException::withMessages(['plan_id' => 'You already have an application or membership at this gym.']);
        }

        $plan = MembershipPlan::findOrFail($data['plan_id']);
        $terms = (int) ($data['prepaid_terms'] ?? 1);

        if ($terms > max(1, (int) $plan->max_prepay_terms)) {
            throw ValidationException::withMessages(['prepaid_terms' => 'This plan can be prepaid for up to '.max(1, (int) $plan->max_prepay_terms).' terms.']);
        }

        $membership = new Membership([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'emergency_contacts' => array_values($data['emergency_contacts']),
            'background' => $data['background'] ?? null,
            'current_rank' => $data['current_rank'] ?? null,
            'training_history' => $data['training_history'] ?? null,
            'health_answers' => array_map(fn ($answer) => filter_var($answer, FILTER_VALIDATE_BOOLEAN), $data['health_answers']),
            'health_notes' => $data['health_notes'] ?? null,
            'waivers' => [...array_keys(config('membership.waivers')), ...($this->needsTerms($gym) ? ['gym_terms'] : [])],
            'signature_name' => $data['signature_name'],
            'signed_on' => today()->toDateString(),
        ]);
        $membership->status = MembershipStatus::Applied;
        $membership->prepaid_terms = $terms;
        // R-111: checked when the gym approves, since the voucher must still be valid then.
        $membership->voucher_code = filled($data['voucher_code'] ?? null) ? strtoupper(trim($data['voucher_code'])) : null;

        if ($request->hasFile('medical_file')) {
            $file = $request->file('medical_file');
            // R-32: kept on the private disk (as in R-15), never in a public folder.
            $membership->forceFill([
                'medical_file_path' => $file->store("medical-files/{$gym->id}", 'local'),
                'medical_file_name' => $file->getClientOriginalName(),
                'medical_file_mime' => $file->getMimeType(),
                'medical_file_size' => $file->getSize(),
            ]);
        }

        $membership->save();
        app(MembershipLedger::class)->log($membership, 'applied', $user);

        // R-71: a person already kept as a lead is linked the moment they apply.
        if ($customer = $linker->customerForEmail($gym, $user->email)) {
            $linker->link($customer, $membership);
        }

        foreach ($this->deciders($gym) as $decider) {
            $decider->notify(new MembershipApplicationSubmitted($membership));
        }

        return response()->json(['membership' => $this->membershipPayload($membership, true, true)], 201);
    }

    private function rules(Gym $gym): array
    {
        $rules = [
            'plan_id' => [
                'required', 'integer',
                Rule::exists('membership_plans', 'id')->where('gym_id', $gym->id)->whereNotNull('published_at'),
            ],
            'emergency_contacts' => ['required', 'array', 'min:1', 'max:'.config('membership.emergency_contacts_max')],
            'emergency_contacts.*' => ['array:name,relationship,phone'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:150'],
            'emergency_contacts.*.relationship' => ['required', 'string', 'max:60'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s()-]{7,30}$/'],
            'background' => ['nullable', 'string', 'max:2000'],
            'current_rank' => ['nullable', 'string', 'max:150'],
            'training_history' => ['nullable', 'string', 'max:2000'],
            'health_answers' => ['required', 'array:'.implode(',', array_keys(config('membership.health_questions')))],
            'health_notes' => ['nullable', 'string', 'max:2000'],
            'medical_file' => [
                config('membership.medical_file.required') ? 'required' : 'nullable', 'file', // (R-136)
                'mimes:'.implode(',', config('membership.medical_file.mimes')),
                'max:'.config('membership.medical_file.max_kb'),
            ],
            'waivers' => ['required', 'array'],
            'signature_name' => ['required', 'string', 'max:150'],
            'prepaid_terms' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
        ];

        foreach (array_keys(config('membership.health_questions')) as $key) {
            $rules["health_answers.{$key}"] = ['required', 'boolean'];
        }

        foreach (array_keys(config('membership.waivers')) as $key) {
            $rules["waivers.{$key}"] = ['accepted'];
        }

        if ($this->needsTerms($gym)) {
            $rules['waivers.gym_terms'] = ['accepted']; // (R-135)
        }

        return $rules;
    }

    private function needsTerms(Gym $gym): bool
    {
        return filled($gym->policies) || $this->gymMedia($gym)['policy_files'] !== [];
    }

    /** Owners, and employees whose roles give membership approval (R-33). */
    private function deciders(Gym $gym)
    {
        return $gym->peopleWithPermission('members.memberships.approve');
    }
}
