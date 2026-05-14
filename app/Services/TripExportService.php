<?php

namespace App\Services;

use App\Models\Trip;

class TripExportService
{
    public function jsonPayload(Trip $trip): array
    {
        $trip->load([
            'collaborators',
            'days.itineraryItems',
            'reservations.flightSegments',
            'reservations.lodgingStay',
            'reservations.flightDetails',
            'costs',
            'packingItems',
            'tasks',
            'documents',
            'reminders',
        ]);

        return [
            'trip' => $trip->only(['name', 'destination', 'starts_on', 'ends_on', 'status', 'summary']),
            'collaborators' => $trip->collaborators,
            'days' => $trip->days,
            'reservations' => $trip->reservations,
            'costs' => $trip->costs,
            'packing_items' => $trip->packingItems,
            'tasks' => $trip->tasks,
            'documents' => $trip->documents,
            'reminders' => $trip->reminders,
            'exported_at' => now()->toIso8601String(),
        ];
    }

    public function ics(Trip $trip): string
    {
        $trip->load(['itineraryItems', 'reservations']);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Vacation Plan//Trip Export//EN',
            'CALSCALE:GREGORIAN',
        ];

        foreach ($trip->itineraryItems as $item) {
            $lines = [...$lines, ...$this->eventLines($item->title, $item->starts_at, $item->ends_at, $item->location_name, $item->description)];
        }

        foreach ($trip->reservations as $reservation) {
            $lines = [...$lines, ...$this->eventLines($reservation->title, $reservation->starts_at, $reservation->ends_at, $reservation->location_name, $reservation->notes)];
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function eventLines(string $title, $startsAt, $endsAt, ?string $location, ?string $description): array
    {
        $start = $startsAt ?? now();
        $end = $endsAt ?? $start->copy()->addHour();

        return [
            'BEGIN:VEVENT',
            'UID:'.sha1($title.$start->toIso8601String()).'@vacation-plan',
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($title),
            $location ? 'LOCATION:'.$this->escape($location) : null,
            $description ? 'DESCRIPTION:'.$this->escape($description) : null,
            'END:VEVENT',
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\n", ',', ';'], ['\\\\', '\n', '\,', '\;'], $value);
    }
}
