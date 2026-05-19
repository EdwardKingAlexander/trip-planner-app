<?php

namespace App\Http\Controllers;

use App\Models\ItineraryItem;
use App\Models\Trip;
use App\Services\TripCollaborationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripItineraryController extends Controller
{
    public function store(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $this->validatedItinerary($request, $trip);

        $item = $trip->itineraryItems()->create($validated + [
            'sort_order' => $trip->itineraryItems()->count(),
        ]);

        $events->record(
            trip: $trip,
            eventType: 'itinerary.created',
            changedArea: 'itinerary',
            summary: "added itinerary item: {$item->title}",
            subject: $item,
        );

        return back()->with('success', 'Itinerary item added.');
    }

    public function update(Request $request, Trip $trip, ItineraryItem $itineraryItem, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($itineraryItem->trip_id === $trip->id, 404);

        $itineraryItem->update($this->validatedItinerary($request, $trip));

        $events->record(
            trip: $trip,
            eventType: 'itinerary.updated',
            changedArea: 'itinerary',
            summary: "updated itinerary item: {$itineraryItem->title}",
            subject: $itineraryItem,
        );

        return back()->with('success', 'Itinerary item updated.');
    }

    public function destroy(Trip $trip, ItineraryItem $itineraryItem, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($itineraryItem->trip_id === $trip->id, 404);

        $title = $itineraryItem->title;
        $events->record(
            trip: $trip,
            eventType: 'itinerary.deleted',
            changedArea: 'itinerary',
            summary: "deleted itinerary item: {$title}",
            subject: $itineraryItem,
        );

        $itineraryItem->delete();

        return back()->with('success', 'Itinerary item deleted.');
    }

    private function validatedItinerary(Request $request, Trip $trip): array
    {
        $tripStartDate = $trip->starts_on->toDateString();

        return $request->validate([
            'trip_day_id' => ['nullable', Rule::exists('trip_days', 'id')->where('trip_id', $trip->id)],
            'type' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['nullable', Rule::date()->afterOrEqual($tripStartDate)],
            'ends_at' => ['nullable', Rule::date()->afterOrEqual($tripStartDate), 'after_or_equal:starts_at'],
            'timezone' => ['required', 'timezone'],
            'is_all_day' => ['boolean'],
            'status' => ['required', 'in:idea,planned,booked,cancelled,completed'],
        ], [
            'starts_at.after_or_equal' => 'The itinerary start time must be on or after the trip start date.',
            'ends_at.after_or_equal' => 'The itinerary end time must be on or after the trip start date and start time.',
        ]);
    }
}
