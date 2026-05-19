<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TripSearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();
        $trips = collect();

        if ($query !== '') {
            $trips = Trip::query()
                ->visibleTo($user)
                ->with(['reservations', 'documents', 'tasks'])
                ->where(function ($builder) use ($query) {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('destination', 'like', "%{$query}%")
                        ->orWhereHas('reservations', fn ($subquery) => $subquery->where('title', 'like', "%{$query}%")->orWhere('booking_reference', 'like', "%{$query}%"))
                        ->orWhereHas('documents', fn ($subquery) => $subquery->where('title', 'like', "%{$query}%"))
                        ->orWhereHas('tasks', fn ($subquery) => $subquery->where('title', 'like', "%{$query}%"));
                })
                ->limit(20)
                ->get()
                ->map(fn (Trip $trip) => [
                    ...$trip->toArray(),
                    'effective_destination_timezone' => $trip->effectiveDestinationTimezone(),
                    'reservations' => $trip->reservations,
                    'documents' => $trip->documents,
                    'tasks' => $trip->tasks,
                ]);
        }

        return Inertia::render('Trips/Search', [
            'query' => $query,
            'trips' => $trips,
        ]);
    }
}
