<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripItineraryController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'trip_day_id' => ['nullable', 'exists:trip_days,id'],
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

        $trip->itineraryItems()->create($validated + [
            'sort_order' => $trip->itineraryItems()->count(),
        ]);

        return back()->with('success', 'Itinerary item added.');
    }
}
