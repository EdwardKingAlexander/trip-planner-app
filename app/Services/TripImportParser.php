<?php

namespace App\Services;

use App\Support\TimezoneLookup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TripImportParser
{
    public function parse(string $sourceType, string $rawText): array
    {
        return match ($sourceType) {
            'ics' => $this->parseIcs($rawText),
            default => $this->parseConfirmationText($rawText),
        };
    }

    private function parseIcs(string $rawText): array
    {
        $events = [];
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $rawText, $matches);

        foreach ($matches[1] as $eventText) {
            $events[] = [
                'type' => 'itinerary_item',
                'item_type' => 'activity',
                'title' => $this->icsValue($eventText, 'SUMMARY') ?? 'Imported event',
                'description' => $this->icsValue($eventText, 'DESCRIPTION'),
                'location_name' => $this->icsValue($eventText, 'LOCATION'),
                'starts_at' => $this->normalizeIcsDate($this->icsValue($eventText, 'DTSTART')),
                'ends_at' => $this->normalizeIcsDate($this->icsValue($eventText, 'DTEND')),
                'timezone' => $this->icsTimezone($eventText) ?? 'UTC',
                'status' => 'planned',
            ];
        }

        return [
            'items' => $events,
            'warnings' => $events ? [] : ['No calendar events were found.'],
        ];
    }

    private function parseConfirmationText(string $rawText): array
    {
        $lines = collect(preg_split('/\R+/', $rawText))->map(fn ($line) => trim($line))->filter();
        $title = $this->matchFirst($rawText, '/(?:hotel|property|airline|reservation|booking)[:#\s-]+(.+)/i') ?? $lines->first() ?? 'Imported reservation';
        $reference = $this->matchFirst($rawText, '/(?:confirmation|booking|reservation|record locator|reference)[:#\s-]+([A-Z0-9-]+)/i');
        $arrivalAirport = $this->matchFirst($rawText, '/(?:arrival|arrive|to)[:#\s-]+([A-Z]{3})\b/i');
        $departureAirport = $this->matchFirst($rawText, '/(?:departure|depart|from)[:#\s-]+([A-Z]{3})\b/i');
        $startsTimezone = TimezoneLookup::airportTimezone($departureAirport) ?? 'UTC';
        $endsTimezone = TimezoneLookup::airportTimezone($arrivalAirport) ?? 'UTC';
        $provider = $this->matchFirst($rawText, '/(?:provider|airline|hotel|property)[:#\s-]+(.+)/i');
        $email = $this->matchFirst($rawText, '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i');
        $phone = $this->matchFirst($rawText, '/(?:phone|tel)[:#\s-]+([+0-9().\s-]+)/i');
        $type = Str::contains(Str::lower($rawText), ['flight', 'airport', 'airline']) ? 'flight' : (Str::contains(Str::lower($rawText), ['hotel', 'check-in', 'check out', 'checkout']) ? 'lodging' : 'custom');
        $dates = $this->findDates($rawText);

        return [
            'items' => [[
                'type' => 'reservation',
                'reservation_type' => $type,
                'title' => Str::limit($title, 140, ''),
                'provider_name' => $provider ? Str::limit($provider, 140, '') : null,
                'booking_reference' => $reference,
                'status' => 'reserved',
                'starts_at' => $dates[0] ?? null,
                'ends_at' => $dates[1] ?? null,
                'starts_timezone' => $startsTimezone,
                'ends_timezone' => $endsTimezone,
                'destination_timezone' => $endsTimezone === 'UTC' ? null : $endsTimezone,
                'contact_phone' => $phone,
                'contact_email' => $email,
                'notes' => Str::limit($rawText, 1500, ''),
            ]],
            'warnings' => $reference ? [] : ['No confirmation number was detected. Review before committing.'],
        ];
    }

    private function icsValue(string $eventText, string $key): ?string
    {
        if (! preg_match('/^'.$key.'(?:;[^:]*)?:(.+)$/mi', $eventText, $match)) {
            return null;
        }

        return trim(str_replace(['\n', '\,'], ["\n", ','], $match[1]));
    }

    private function icsTimezone(string $eventText): ?string
    {
        return preg_match('/^DTSTART;TZID=([^:]+):/mi', $eventText, $match) ? $match[1] : null;
    }

    private function normalizeIcsDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        try {
            if (preg_match('/^\d{8}T\d{6}Z$/', $value)) {
                return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC')->toDateTimeString();
            }

            if (preg_match('/^\d{8}T\d{6}$/', $value)) {
                return Carbon::createFromFormat('Ymd\THis', $value, 'UTC')->toDateTimeString();
            }

            if (preg_match('/^\d{8}$/', $value)) {
                return Carbon::createFromFormat('Ymd', $value, 'UTC')->startOfDay()->toDateTimeString();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function matchFirst(string $text, string $pattern): ?string
    {
        return preg_match($pattern, $text, $match) ? trim($match[1] ?? $match[0]) : null;
    }

    private function findDates(string $text): array
    {
        preg_match_all('/\b(?:20\d{2})[-\/.](?:0?[1-9]|1[0-2])[-\/.](?:0?[1-9]|[12]\d|3[01])(?:[ T](?:[01]?\d|2[0-3]):[0-5]\d)?\b/', $text, $matches);

        return collect($matches[0])
            ->take(2)
            ->map(fn ($date) => Carbon::parse($date)->toDateTimeString())
            ->all();
    }
}
