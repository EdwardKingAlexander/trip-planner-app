<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Trip;
use App\Services\TripCollaborationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripReservationController extends Controller
{
    public function store(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $this->validatedReservation($request);

        $reservation = DB::transaction(function () use ($trip, $validated) {
            $reservation = $trip->reservations()->create($this->reservationAttributes($validated));

            $this->syncReservationDetails($reservation, $validated);

            return $reservation;
        });

        $events->record(
            trip: $trip,
            eventType: 'reservation.created',
            changedArea: 'reservations',
            summary: "added reservation: {$reservation->title}",
            subject: $reservation,
        );

        return back()->with('success', 'Reservation added.');
    }

    public function update(Request $request, Trip $trip, Reservation $reservation, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($reservation->trip_id === $trip->id, 404);

        $validated = $this->validatedReservation($request);

        DB::transaction(function () use ($reservation, $validated) {
            $reservation->update($this->reservationAttributes($validated));

            $this->syncReservationDetails($reservation, $validated);
        });

        $events->record(
            trip: $trip,
            eventType: 'reservation.updated',
            changedArea: 'reservations',
            summary: "updated reservation: {$reservation->title}",
            subject: $reservation,
        );

        return back()->with('success', 'Reservation updated.');
    }

    public function destroy(Trip $trip, Reservation $reservation, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($reservation->trip_id === $trip->id, 404);

        $title = $reservation->title;
        $events->record(
            trip: $trip,
            eventType: 'reservation.deleted',
            changedArea: 'reservations',
            summary: "deleted reservation: {$title}",
            subject: $reservation,
        );

        $reservation->delete();

        return back()->with('success', 'Reservation deleted.');
    }

    private function validatedReservation(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:160'],
            'provider_name' => ['nullable', 'string', 'max:160'],
            'booking_reference' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:researching,reserved,confirmed,checked_in,cancelled,completed'],
            'starts_at' => ['nullable', 'date'],
            'starts_timezone' => ['required', 'timezone'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'ends_timezone' => ['required', 'timezone'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:240'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'airline' => ['nullable', 'string', 'max:120'],
            'flight_number' => ['nullable', 'string', 'max:40'],
            'departure_airport' => ['nullable', 'string', 'max:20'],
            'arrival_airport' => ['nullable', 'string', 'max:20'],
            'property_name' => ['nullable', 'string', 'max:160'],
            'room_type' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function reservationAttributes(array $validated): array
    {
        return collect($validated)->except([
            'airline',
            'flight_number',
            'departure_airport',
            'arrival_airport',
            'property_name',
            'room_type',
        ])->all();
    }

    private function syncReservationDetails(Reservation $reservation, array $validated): void
    {
        if ($reservation->type === 'flight') {
            $reservation->flightSegments()->updateOrCreate(
                ['segment_order' => 0],
                [
                    'airline' => $validated['airline'] ?? $reservation->provider_name,
                    'flight_number' => $validated['flight_number'] ?? null,
                    'confirmation_code' => $reservation->booking_reference,
                    'departure_airport' => $validated['departure_airport'] ?? null,
                    'arrival_airport' => $validated['arrival_airport'] ?? null,
                    'departs_at' => $reservation->starts_at,
                    'departure_timezone' => $reservation->starts_timezone,
                    'arrives_at' => $reservation->ends_at,
                    'arrival_timezone' => $reservation->ends_timezone,
                    'segment_order' => 0,
                ],
            );
        }

        if ($reservation->type !== 'flight') {
            $reservation->flightSegments()->delete();
        }

        if ($reservation->type === 'lodging') {
            $reservation->lodgingStay()->updateOrCreate(
                [],
                [
                    'property_name' => $validated['property_name'] ?? $reservation->title,
                    'room_type' => $validated['room_type'] ?? null,
                    'check_in_at' => $reservation->starts_at,
                    'check_in_timezone' => $reservation->starts_timezone,
                    'check_out_at' => $reservation->ends_at,
                    'check_out_timezone' => $reservation->ends_timezone,
                    'address' => $reservation->address,
                    'phone' => $reservation->contact_phone,
                ],
            );
        }

        if ($reservation->type !== 'lodging') {
            $reservation->lodgingStay()->delete();
        }
    }
}
