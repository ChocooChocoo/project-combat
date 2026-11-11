<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A closing time is after the opening time. 00:00 is the exception: it means midnight,
 * so 08:00 to 00:00 is a normal day and 00:00 to 00:00 is open 24 hours.
 * Closing after midnight (18:00 to 02:00) is not supported.
 */
class ClosingTime implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $open = data_get($this->data, preg_replace('/\.close$/', '.open', $attribute));

        if (! is_string($value) || ! is_string($open) || $value === '00:00' || $value > $open) {
            return;
        }

        $fail('Closing time must be after opening time. Use 12:00 AM for midnight.');
    }
}
