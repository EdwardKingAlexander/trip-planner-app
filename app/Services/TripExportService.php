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
            'trip' => [
                ...$trip->only(['name', 'destination', 'starts_on', 'ends_on', 'status', 'summary']),
                'destination_timezone' => $trip->destination_timezone,
                'home_timezone' => $trip->home_timezone,
                'effective_destination_timezone' => $trip->effectiveDestinationTimezone(),
                'effective_home_timezone' => $trip->effectiveHomeTimezone(),
            ],
            'collaborators' => $trip->collaborators,
            'days' => $trip->days->map(fn ($day) => [
                ...$day->toArray(),
                'label' => $trip->dayLabelFor($day),
                'itinerary_items' => $day->itineraryItems,
            ]),
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
            'X-WR-TIMEZONE:'.$this->escape($trip->effectiveDestinationTimezone()),
            ...$this->tripAllDayEventLines($trip),
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

        return array_filter([
            'BEGIN:VEVENT',
            'UID:'.sha1($title.$start->toIso8601String()).'@vacation-plan',
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($title),
            $location ? 'LOCATION:'.$this->escape($location) : null,
            $description ? 'DESCRIPTION:'.$this->escape($description) : null,
            'END:VEVENT',
        ]);
    }

    private function tripAllDayEventLines(Trip $trip): array
    {
        $startsOn = $trip->starts_on->format('Ymd');
        $endsOn = $trip->ends_on->copy()->addDay()->format('Ymd');

        return array_filter([
            'BEGIN:VEVENT',
            'UID:trip-'.$trip->id.'@vacation-plan',
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$startsOn,
            'DTEND;VALUE=DATE:'.$endsOn,
            'SUMMARY:'.$this->escape($trip->name),
            'LOCATION:'.$this->escape($trip->destination),
            $trip->summary ? 'DESCRIPTION:'.$this->escape($trip->summary) : null,
            'END:VEVENT',
        ]);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\n", ',', ';'], ['\\\\', '\n', '\,', '\;'], $value);
    }
}
