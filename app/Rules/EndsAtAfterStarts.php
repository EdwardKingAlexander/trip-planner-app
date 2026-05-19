<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

class EndsAtAfterStarts implements ValidationRule
{
    public function __construct(
        private readonly ?string $startsAt,
        private readonly ?string $startsTimezone,
        private readonly ?string $endsTimezone,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->startsAt || ! $value || ! $this->startsTimezone || ! $this->endsTimezone) {
            return;
        }

        try {
            $start = Carbon::parse($this->startsAt, $this->startsTimezone);
            $end = Carbon::parse((string) $value, $this->endsTimezone);
        } catch (\Throwable) {
            $fail('The end date and time could not be parsed.');

            return;
        }

        if ($end->lt($start)) {
            $fail('The end date and time must be at or after the start date and time.');
        }
    }
}
