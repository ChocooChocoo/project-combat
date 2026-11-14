<?php

namespace App\Support;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** (R-129) Age rules at sign-up: minimum age, adult owners, guardian consent for minors. */
class Guardian
{
    public static function rules(): array
    {
        return [
            'birthdate' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s()-]{7,30}$/'],
            'guardian_relationship' => ['nullable', 'string', 'max:60'],
            'guardian_consent' => ['nullable', 'boolean'],
        ];
    }

    /** Throws when the age does not fit; returns the guardian columns to save. */
    public static function check(array $data, AccountType $type): array
    {
        $age = Carbon::parse($data['birthdate'])->age;

        if ($age < User::MIN_AGE) {
            throw ValidationException::withMessages(['birthdate' => 'Children under 7 cannot have an account.']);
        }

        if ($age >= User::ADULT_AGE) {
            return ['guardian_name' => null, 'guardian_phone' => null, 'guardian_relationship' => null, 'guardian_consent_at' => null];
        }

        if ($type === AccountType::Owner) {
            throw ValidationException::withMessages(['birthdate' => 'Gym owners must be 18 or older.']);
        }

        $errors = [];
        foreach ([
            'guardian_name' => "Add your parent or guardian's name.",
            'guardian_phone' => "Add your parent or guardian's phone number.",
            'guardian_relationship' => "Add your parent or guardian's relationship to you.",
        ] as $field => $message) {
            if (blank($data[$field] ?? null)) {
                $errors[$field] = $message;
            }
        }
        if (! filter_var($data['guardian_consent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors['guardian_consent'] = 'Your parent or guardian must agree before you can sign up.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'guardian_name' => $data['guardian_name'],
            'guardian_phone' => $data['guardian_phone'],
            'guardian_relationship' => $data['guardian_relationship'],
            'guardian_consent_at' => now(),
        ];
    }
}
