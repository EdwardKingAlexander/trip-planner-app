<?php

namespace App\Rules;

use App\Support\TimezoneLookup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class TimezoneRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! TimezoneLookup::isValid($value)) {
            $fail('The :attribute must be a valid timezone identifier.');
        }
    }
}
