<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class TripDates
{
    public static function date(?string $value, string $timezone): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value, $timezone)->startOfDay();
    }

    public static function formatDate(?string $value, string $timezone, string $format = 'M j, Y'): string
    {
        return self::date($value, $timezone)?->translatedFormat($format) ?? 'Flexible';
    }

    public static function formatDateTime(?string $value, ?string $timezone, string $format = 'M j, g:i A'): string
    {
        if ($value === null || $value === '') {
            return 'Time TBD';
        }

        return Carbon::parse($value)
            ->setTimezone($timezone ?: config('app.timezone'))
            ->translatedFormat($format);
    }
}
