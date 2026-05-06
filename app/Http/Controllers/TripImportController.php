<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripImportBatch;
use App\Services\TripAutomationService;
use App\Services\TripCollaborationEventService;
use App\Services\TripImportParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripImportController extends Controller
{
    public function store(Request $request, Trip $trip, TripImportParser $parser): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'source_type' => ['required', 'in:confirmation,ics'],
            'raw_text' => ['required', 'string', 'max:20000'],
        ]);

        $payload = $parser->parse($validated['source_type'], $validated['raw_text']);

        $trip->importBatches()->create([
            'user_id' => $request->user()->id,
            'source_type' => $validated['source_type'],
            'raw_text' => $validated['raw_text'],
            'parsed_payload' => $payload,
            'status' => 'reviewing',
        ]);

        return back()->with('success', 'Import parsed and ready for review.');
    }

    public function commit(Trip $trip, TripImportBatch $importBatch, TripAutomationService $automation, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($importBatch->trip_id === $trip->id, 404);

        DB::transaction(function () use ($trip, $importBatch) {
            foreach ($importBatch->parsed_payload['items'] ?? [] as $item) {
                if (($item['type'] ?? null) === 'reservation') {
                    $duplicate = $trip->reservations()
                        ->where('title', $item['title'])
                        ->when($item['booking_reference'] ?? null, fn ($query, $reference) => $query->orWhere('booking_reference', $reference))
                        ->exists();

                    if (! $duplicate) {
                        $trip->reservations()->create([
                            'type' => $item['reservation_type'] ?? 'custom',
                            'title' => $item['title'],
                            'provider_name' => $item['provider_name'] ?? null,
                            'booking_reference' => $item['booking_reference'] ?? null,
                            'status' => $item['status'] ?? 'reserved',
                            'starts_at' => $item['starts_at'] ?? null,
                            'starts_timezone' => $item['starts_timezone'] ?? 'UTC',
                            'ends_at' => $item['ends_at'] ?? null,
                            'ends_timezone' => $item['ends_timezone'] ?? 'UTC',
                            'contact_phone' => $item['contact_phone'] ?? null,
                            'contact_email' => $item['contact_email'] ?? null,
                            'notes' => $item['notes'] ?? null,
                        ]);
                    }
                }

                if (($item['type'] ?? null) === 'itinerary_item') {
                    $day = $trip->days()->whereDate('date', optional($item['starts_at'] ? now()->parse($item['starts_at']) : null)->toDateString())->first();

                    $trip->itineraryItems()->create([
                        'trip_day_id' => $day?->id,
                        'type' => $item['item_type'] ?? 'activity',
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'location_name' => $item['location_name'] ?? null,
                        'starts_at' => $item['starts_at'] ?? null,
                        'ends_at' => $item['ends_at'] ?? null,
                        'timezone' => $item['timezone'] ?? 'UTC',
                        'status' => $item['status'] ?? 'planned',
                        'sort_order' => $trip->itineraryItems()->count(),
                    ]);
                }
            }

            $importBatch->update([
                'status' => 'committed',
                'reviewed_at' => now(),
            ]);
        });

        $automation->refresh($trip);

        $events->record(
            trip: $trip,
            eventType: 'import.committed',
            changedArea: 'imports',
            summary: "committed import batch #{$importBatch->id}",
            subject: $importBatch,
        );

        return back()->with('success', 'Import committed to the trip.');
    }

    public function discard(Trip $trip, TripImportBatch $importBatch, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($importBatch->trip_id === $trip->id, 404);

        $importBatch->update([
            'status' => 'discarded',
            'reviewed_at' => now(),
        ]);

        $events->record(
            trip: $trip,
            eventType: 'import.discarded',
            changedArea: 'imports',
            summary: "discarded import batch #{$importBatch->id}",
            subject: $importBatch,
        );

        return back()->with('success', 'Import discarded.');
    }
}
