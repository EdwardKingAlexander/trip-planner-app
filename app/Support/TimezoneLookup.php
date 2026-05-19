<?php

namespace App\Support;

final class TimezoneLookup
{
    /**
     * @return array<string, string>
     */
    public static function destinationLookup(): array
    {
        return [
            'manila' => 'Asia/Manila',
            'philippines' => 'Asia/Manila',
            'tokyo' => 'Asia/Tokyo',
            'japan' => 'Asia/Tokyo',
            'london' => 'Europe/London',
            'paris' => 'Europe/Paris',
            'rome' => 'Europe/Rome',
            'barcelona' => 'Europe/Madrid',
            'amsterdam' => 'Europe/Amsterdam',
            'berlin' => 'Europe/Berlin',
            'reykjavik' => 'Atlantic/Reykjavik',
            'iceland' => 'Atlantic/Reykjavik',
            'sydney' => 'Australia/Sydney',
            'auckland' => 'Pacific/Auckland',
            'bali' => 'Asia/Makassar',
            'bangkok' => 'Asia/Bangkok',
            'singapore' => 'Asia/Singapore',
            'hong kong' => 'Asia/Hong_Kong',
            'seoul' => 'Asia/Seoul',
            'dubai' => 'Asia/Dubai',
            'new york' => 'America/New_York',
            'los angeles' => 'America/Los_Angeles',
            'san francisco' => 'America/Los_Angeles',
            'chicago' => 'America/Chicago',
            'denver' => 'America/Denver',
            'honolulu' => 'Pacific/Honolulu',
            'hawaii' => 'Pacific/Honolulu',
            'cancun' => 'America/Cancun',
            'mexico city' => 'America/Mexico_City',
            'buenos aires' => 'America/Argentina/Buenos_Aires',
            'rio' => 'America/Sao_Paulo',
            'cape town' => 'Africa/Johannesburg',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function airportLookup(): array
    {
        return [
            'LAX' => 'America/Los_Angeles',
            'SFO' => 'America/Los_Angeles',
            'DEN' => 'America/Denver',
            'JFK' => 'America/New_York',
            'EWR' => 'America/New_York',
            'ORD' => 'America/Chicago',
            'MNL' => 'Asia/Manila',
            'NRT' => 'Asia/Tokyo',
            'HND' => 'Asia/Tokyo',
            'LHR' => 'Europe/London',
            'LGW' => 'Europe/London',
            'CDG' => 'Europe/Paris',
            'FCO' => 'Europe/Rome',
            'SYD' => 'Australia/Sydney',
            'SIN' => 'Asia/Singapore',
            'ICN' => 'Asia/Seoul',
            'DXB' => 'Asia/Dubai',
        ];
    }

    /**
     * @return list<string>
     */
    public static function identifiers(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    public static function isValid(?string $timezone): bool
    {
        if ($timezone === null || $timezone === '') {
            return false;
        }

        return in_array($timezone, self::identifiers(), true);
    }

    public static function guessDestinationTimezone(?string $destination): ?string
    {
        $normalized = mb_strtolower(trim((string) $destination));

        if ($normalized === '') {
            return null;
        }

        foreach (self::destinationLookup() as $needle => $timezone) {
            if (str_contains($normalized, $needle)) {
                return $timezone;
            }
        }

        return null;
    }

    public static function airportTimezone(?string $airportCode): ?string
    {
        $code = mb_strtoupper(trim((string) $airportCode));

        return self::airportLookup()[$code] ?? null;
    }
}
