<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Trip;
use App\Rules\EndsAtAfterStarts;
use App\Services\TripCollaborationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $flightDetails = $request->input('flight_details');

        if (is_array($flightDetails) && isset($flightDetails['currency'])) {
            $flightDetails['currency'] = mb_strtoupper((string) $flightDetails['currency']);
            $request->merge(['flight_details' => $flightDetails]);
        }

        $timezones = \DateTimeZone::listIdentifiers();

        return $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:160'],
            'provider_name' => ['nullable', 'string', 'max:160'],
            'booking_reference' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:researching,reserved,confirmed,checked_in,cancelled,completed'],
            'starts_at' => ['nullable', 'date'],
            'starts_timezone' => ['required', Rule::in($timezones)],
            'ends_at' => [
                'nullable',
                'date',
                new EndsAtAfterStarts(
                    $request->input('starts_at'),
                    $request->input('starts_timezone'),
                    $request->input('ends_timezone'),
                ),
            ],
            'ends_timezone' => ['required', Rule::in($timezones)],
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
            'flight_details' => ['nullable', 'array'],
            'flight_details.cabin_class' => ['nullable', 'string', Rule::in(['economy', 'premium_economy', 'business', 'first'])],
            'flight_details.currency' => ['nullable', 'string', 'size:3'],
            'flight_details.carry_on_size' => ['nullable', 'string', 'max:120'],
            'flight_details.carry_on_weight' => ['nullable', 'string', 'max:40'],
            'flight_details.carry_on_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
            'flight_details.personal_item_size' => ['nullable', 'string', 'max:120'],
            'flight_details.personal_item_weight' => ['nullable', 'string', 'max:40'],
            'flight_details.personal_item_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
            'flight_details.checked_bag_size' => ['nullable', 'string', 'max:120'],
            'flight_details.checked_bag_weight' => ['nullable', 'string', 'max:40'],
            'flight_details.checked_bag_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
            'flight_details.additional_checked_bag_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
            'flight_details.additional_checked_bag_allowance' => ['nullable', 'string', 'max:80'],
            'flight_details.visa_requirement' => ['nullable', 'string', 'max:1000'],
            'flight_details.passport_validity_rule' => ['nullable', 'string', 'max:1000'],
            'flight_details.layover_notes' => ['nullable', 'string', 'max:1000'],
            'flight_details.online_check_in_opens' => ['nullable', 'string', 'max:80'],
            'flight_details.boarding_closes' => ['nullable', 'string', 'max:80'],
            'flight_details.notes' => ['nullable', 'string', 'max:1000'],
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
            'flight_details',
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

        $flightDetails = $validated['flight_details'] ?? null;
        $hasAnyFlightDetail = $flightDetails && collect($flightDetails)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->isNotEmpty();

        if ($reservation->type === 'flight' && $hasAnyFlightDetail) {
            $reservation->flightDetails()->updateOrCreate([], $this->cleanFlightDetailsPayload($flightDetails));

            return;
        }

        $reservation->flightDetails()->delete();
    }

    private function cleanFlightDetailsPayload(array $payload): array
    {
        return [
            'cabin_class' => $payload['cabin_class'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'carry_on_size' => $payload['carry_on_size'] ?? null,
            'carry_on_weight' => $payload['carry_on_weight'] ?? null,
            'carry_on_fee' => $payload['carry_on_fee'] ?? null,
            'personal_item_size' => $payload['personal_item_size'] ?? null,
            'personal_item_weight' => $payload['personal_item_weight'] ?? null,
            'personal_item_fee' => $payload['personal_item_fee'] ?? null,
            'checked_bag_size' => $payload['checked_bag_size'] ?? null,
            'checked_bag_weight' => $payload['checked_bag_weight'] ?? null,
            'checked_bag_fee' => $payload['checked_bag_fee'] ?? null,
            'additional_checked_bag_fee' => $payload['additional_checked_bag_fee'] ?? null,
            'additional_checked_bag_allowance' => $payload['additional_checked_bag_allowance'] ?? null,
            'visa_requirement' => $payload['visa_requirement'] ?? null,
            'passport_validity_rule' => $payload['passport_validity_rule'] ?? null,
            'layover_notes' => $payload['layover_notes'] ?? null,
            'online_check_in_opens' => $payload['online_check_in_opens'] ?? null,
            'boarding_closes' => $payload['boarding_closes'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];
    }
}
