<?php

use App\Models\Trip;
use App\Models\User;

function crossTimezoneReservationPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'flight',
        'title' => 'LAX to Manila',
        'provider_name' => 'United',
        'booking_reference' => 'UA123',
        'status' => 'reserved',
        'starts_at' => '2026-09-26T22:00',
        'starts_timezone' => 'America/Los_Angeles',
        'ends_at' => '2026-09-28T06:00',
        'ends_timezone' => 'Asia/Manila',
        'location_name' => 'Manila',
        'airline' => 'United',
        'flight_number' => 'UA123',
        'departure_airport' => 'LAX',
        'arrival_airport' => 'MNL',
    ], $overrides);
}

test('reservation validation accepts a cross dateline flight in real time order', function () {
    $user = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Manila',
        'destination' => 'Manila, Philippines',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ]);

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), crossTimezoneReservationPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $reservation = $trip->reservations()->firstOrFail();

    expect($reservation->flightSegments)->toHaveCount(1)
        ->and($reservation->flightSegments->first()->departure_timezone)->toBe('America/Los_Angeles')
        ->and($reservation->flightSegments->first()->arrival_timezone)->toBe('Asia/Manila');
});

test('reservation validation rejects an end instant before the start instant', function () {
    $user = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Tokyo',
        'destination' => 'Tokyo, Japan',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ]);

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), crossTimezoneReservationPayload([
            'starts_at' => '2026-09-26T22:00',
            'starts_timezone' => 'Asia/Tokyo',
            'ends_at' => '2026-09-26T05:00',
            'ends_timezone' => 'America/Los_Angeles',
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('ends_at');
});

test('reservation validation uppercases flight detail currency on save', function () {
    $user = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Manila',
        'destination' => 'Manila, Philippines',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ]);

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), crossTimezoneReservationPayload([
            'flight_details' => [
                'currency' => 'usd',
                'carry_on_fee' => '25',
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($trip->reservations()->firstOrFail()->flightDetails->currency)->toBe('USD');
});
