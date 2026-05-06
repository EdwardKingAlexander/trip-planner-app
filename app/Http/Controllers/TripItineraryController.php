<?php

namespace App\Http\Controllers;

use App\Models\ItineraryItem;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripItineraryController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $this->validatedItinerary($request, $trip);

        $trip->itineraryItems()->create($validated + [
            'sort_order' => $trip->itineraryItems()->count(),
        ]);

        return back()->with('success', 'Itinerary item added.');
    }

    public function update(Request $request, Trip $trip, ItineraryItem $itineraryItem): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($itineraryItem->trip_id === $trip->id, 404);

        $itineraryItem->update($this->validatedItinerary($request, $trip));

        return back()->with('success', 'Itinerary item updated.');
    }

    private function validatedItinerary(Request $request, Trip $trip): array
    {
        return $request->validate([
            'trip_day_id' => ['nullable', Rule::exists('trip_days', 'id')->where('trip_id', $trip->id)],
            'type' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['required', 'timezone'],
            'is_all_day' => ['boolean'],
            'status' => ['required', 'in:idea,planned,booked,cancelled,completed'],
        ]);
    }
}
