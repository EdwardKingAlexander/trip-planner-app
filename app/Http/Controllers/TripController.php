<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $trips = Trip::query()
            ->visibleTo($user)
            ->withCount(['days', 'itineraryItems', 'reservations', 'tasks', 'documents', 'collaborators'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Trip $trip) => $this->tripSummary($trip, $user->id));

        return Inertia::render('Trips/Index', [
            'trips' => $trips,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'total' => $trips->count(),
                'upcoming' => $trips->where('bucket', 'upcoming')->count(),
                'active' => $trips->where('bucket', 'active')->count(),
                'shared' => $trips->where('is_owner', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTrip($request);

        $trip = DB::transaction(function () use ($request, $validated) {
            $trip = $request->user()->trips()->create($validated);
            $trip->syncDays($validated['starts_on'], $validated['ends_on']);

            return $trip;
        });

        return to_route('trips.show', $trip)->with('success', 'Trip created.');
    }

    public function show(Request $request, Trip $trip): Response
    {
        $this->authorize('view', $trip);

        $trip->load([
            'collaborators',
            'days.itineraryItems',
            'reservations.flightSegments',
            'reservations.lodgingStay',
            'costs',
            'packingItems',
            'tasks',
            'documents',
            'reminders',
        ]);

        return Inertia::render('Trips/Show', [
            'trip' => $this->tripDetail($trip, $request->user()->id),
        ]);
    }

    public function update(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        DB::transaction(function () use ($request, $trip) {
            $validated = $this->validateTrip($request);
            $trip->update($validated);
            $trip->syncDays($validated['starts_on'], $validated['ends_on']);
        });

        return back()->with('success', 'Trip updated.');
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        $this->authorize('delete', $trip);

        $trip->delete();

        return to_route('trips.index')->with('success', 'Trip deleted.');
    }

    private function validateTrip(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:160'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', 'in:draft,planned,active,completed,archived'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'cover_theme' => ['nullable', 'string', 'max:40'],
        ]);
    }

    private function tripSummary(Trip $trip, int $userId): array
    {
        return [
            'id' => $trip->id,
            'name' => $trip->name,
            'destination' => $trip->destination,
            'starts_on' => $trip->starts_on->toDateString(),
            'ends_on' => $trip->ends_on->toDateString(),
            'status' => $trip->status,
            'summary' => $trip->summary,
            'cover_theme' => $trip->cover_theme,
            'length' => $trip->tripLengthLabel(),
            'bucket' => $trip->timingBucket(),
            'is_owner' => $trip->user_id === $userId,
            'counts' => [
                'days' => $trip->days_count ?? $trip->days()->count(),
                'items' => $trip->itinerary_items_count ?? $trip->itineraryItems()->count(),
                'reservations' => $trip->reservations_count ?? $trip->reservations()->count(),
                'tasks' => $trip->tasks_count ?? $trip->tasks()->count(),
                'documents' => $trip->documents_count ?? $trip->documents()->count(),
                'collaborators' => $trip->collaborators_count ?? $trip->collaborators()->count(),
            ],
        ];
    }

    private function tripDetail(Trip $trip, int $userId): array
    {
        $trip->loadCount(['days', 'itineraryItems', 'reservations', 'tasks', 'documents', 'collaborators']);

        return [
            ...$this->tripSummary($trip, $userId),
            'can_edit' => $trip->canBeEditedBy(request()->user()),
            'can_share' => $trip->user_id === $userId,
            'days' => $trip->days->map(fn ($day) => [
                'id' => $day->id,
                'date' => $day->date->toDateString(),
                'title' => $day->title,
                'notes' => $day->notes,
                'items' => $day->itineraryItems->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'description' => $item->description,
                    'location_name' => $item->location_name,
                    'starts_at' => $item->starts_at?->toIso8601String(),
                    'ends_at' => $item->ends_at?->toIso8601String(),
                    'timezone' => $item->timezone,
                    'status' => $item->status,
                    'is_all_day' => $item->is_all_day,
                ]),
            ]),
            'reservations' => $trip->reservations->map(fn ($reservation) => [
                'id' => $reservation->id,
                'type' => $reservation->type,
                'title' => $reservation->title,
                'provider_name' => $reservation->provider_name,
                'booking_reference' => $reservation->booking_reference,
                'status' => $reservation->status,
                'starts_at' => $reservation->starts_at?->toIso8601String(),
                'starts_timezone' => $reservation->starts_timezone,
                'ends_at' => $reservation->ends_at?->toIso8601String(),
                'ends_timezone' => $reservation->ends_timezone,
                'location_name' => $reservation->location_name,
                'address' => $reservation->address,
                'contact_phone' => $reservation->contact_phone,
                'contact_email' => $reservation->contact_email,
                'notes' => $reservation->notes,
                'flight_segments' => $reservation->flightSegments,
                'lodging_stay' => $reservation->lodgingStay,
            ]),
            'costs' => $trip->costs,
            'packing_items' => $trip->packingItems,
            'tasks' => $trip->tasks,
            'documents' => $trip->documents,
            'reminders' => $trip->reminders,
            'collaborators' => $trip->collaborators,
        ];
    }
}
