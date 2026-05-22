<?php

namespace App\Http\Controllers;

use App\Models\ItineraryItem;
use App\Models\Trip;
use App\Services\TripCollaborationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ItineraryItemNoteController extends Controller
{
    public function store(Request $request, Trip $trip, ItineraryItem $itineraryItem, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($itineraryItem->trip_id === $trip->id, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $itineraryItem->notes()->create($validated);

        $events->record(
            trip: $trip,
            eventType: 'itinerary.note.created',
            changedArea: 'itinerary',
            summary: "commented on itinerary item: {$itineraryItem->title}",
            subject: $itineraryItem,
        );

        return back()->with('success', 'Comment added.');
    }
}
