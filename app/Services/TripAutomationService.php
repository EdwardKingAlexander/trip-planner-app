<?php

namespace App\Services;

use App\Models\Trip;

class TripAutomationService
{
    public function refresh(Trip $trip): void
    {
        $trip->load(['days', 'reservations', 'packingItems', 'reminders']);

        if ($trip->packingItems->isEmpty()) {
            $this->suggest($trip, 'packing_template', 'Add a starter packing list for this trip.', [
                'items' => ['Passport / ID', 'Chargers', 'Medication', 'Travel outfit', 'Toiletries'],
            ]);
        }

        if ($trip->reservations->where('type', 'lodging')->isEmpty()) {
            $this->suggest($trip, 'missing_lodging', 'No lodging reservation is attached yet.', [
                'trip_nights' => max(0, $trip->starts_on->diffInDays($trip->ends_on)),
            ]);
        }

        if ($trip->reminders->isEmpty()) {
            $this->suggest($trip, 'starter_reminders', 'Create reminder prompts for check-in, documents, and packing.', [
                'labels' => ['Check in for flight', 'Review documents', 'Finish packing'],
            ]);
        }
    }

    private function suggest(Trip $trip, string $type, string $summary, array $payload): void
    {
        $trip->automationSuggestions()->firstOrCreate(
            [
                'suggestion_type' => $type,
                'summary' => $summary,
            ],
            ['payload' => $payload],
        );
    }
}
