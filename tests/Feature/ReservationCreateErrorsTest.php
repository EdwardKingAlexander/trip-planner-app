<?php

use App\Models\Trip;
use App\Models\User;

function reservationTrip(): array
{
    $user = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Manila',
        'destination' => 'Manila, Philippines',
        'destination_timezone' => 'Asia/Manila',
        'home_timezone' => 'America/Los_Angeles',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ]);

    return [$user, $trip];
}

function validReservationPayload(array $overrides = []): array
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
        'address' => '',
        'notes' => '',
        'airline' => 'United',
        'flight_number' => 'UA123',
        'departure_airport' => 'LAX',
        'arrival_airport' => 'MNL',
    ], $overrides);
}

test('users can create a flight reservation with minimum details', function () {
    [$user, $trip] = reservationTrip();

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), validReservationPayload([
            'provider_name' => '',
            'booking_reference' => '',
            'starts_at' => '',
            'ends_at' => '',
            'location_name' => '',
            'airline' => '',
            'flight_number' => '',
            'departure_airport' => '',
            'arrival_airport' => '',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Reservation added.');

    expect($trip->reservations()->where('title', 'LAX to Manila')->exists())->toBeTrue();
});

test('reservation creation returns visible validation errors for missing title', function () {
    [$user, $trip] = reservationTrip();

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), validReservationPayload(['title' => '']))
        ->assertRedirect()
        ->assertSessionHasErrors('title');
});

test('reservation creation rejects shorthand timezone codes', function () {
    [$user, $trip] = reservationTrip();

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), validReservationPayload(['starts_timezone' => 'PST']))
        ->assertRedirect()
        ->assertSessionHasErrors('starts_timezone');
});

test('reservation creation returns errors for constrained fields', function (string $field, mixed $value) {
    [$user, $trip] = reservationTrip();

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), validReservationPayload([$field => $value]))
        ->assertRedirect()
        ->assertSessionHasErrors($field);
})->with([
    'oversized provider' => ['provider_name', str_repeat('A', 161)],
    'oversized booking reference' => ['booking_reference', str_repeat('A', 121)],
    'oversized notes' => ['notes', str_repeat('A', 2001)],
]);

test('reservation update returns contact email validation errors', function () {
    [$user, $trip] = reservationTrip();
    $reservation = $trip->reservations()->create([
        'type' => 'custom',
        'title' => 'Dinner',
        'status' => 'reserved',
        'starts_timezone' => 'Asia/Manila',
        'ends_timezone' => 'Asia/Manila',
    ]);

    $this->actingAs($user)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), validReservationPayload([
            'type' => 'custom',
            'contact_email' => 'not-an-email',
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('contact_email');
});

test('viewer collaborators cannot create reservations', function () {
    [$owner, $trip] = reservationTrip();
    $viewer = User::factory()->create();
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->post(route('trips.reservations.store', $trip), validReservationPayload())
        ->assertForbidden();

    expect($trip->reservations()->count())->toBe(0)
        ->and($owner)->toBeInstanceOf(User::class);
});
