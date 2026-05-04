<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripReservationController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
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

        DB::transaction(function () use ($trip, $validated) {
            $reservation = $trip->reservations()->create(collect($validated)->except([
                'airline',
                'flight_number',
                'departure_airport',
                'arrival_airport',
                'property_name',
                'room_type',
            ])->all());

            if ($reservation->type === 'flight') {
                $reservation->flightSegments()->create([
                    'airline' => $validated['airline'] ?? $reservation->provider_name,
                    'flight_number' => $validated['flight_number'] ?? null,
                    'confirmation_code' => $reservation->booking_reference,
                    'departure_airport' => $validated['departure_airport'] ?? null,
                    'arrival_airport' => $validated['arrival_airport'] ?? null,
                    'departs_at' => $reservation->starts_at,
                    'departure_timezone' => $reservation->starts_timezone,
                    'arrives_at' => $reservation->ends_at,
                    'arrival_timezone' => $reservation->ends_timezone,
                ]);
            }

            if ($reservation->type === 'lodging') {
                $reservation->lodgingStay()->create([
                    'property_name' => $validated['property_name'] ?? $reservation->title,
                    'room_type' => $validated['room_type'] ?? null,
                    'check_in_at' => $reservation->starts_at,
                    'check_in_timezone' => $reservation->starts_timezone,
                    'check_out_at' => $reservation->ends_at,
                    'check_out_timezone' => $reservation->ends_timezone,
                    'address' => $reservation->address,
                    'phone' => $reservation->contact_phone,
                ]);
            }
        });

        return back()->with('success', 'Reservation added.');
    }
}
