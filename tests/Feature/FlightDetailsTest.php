<?php

use App\Models\Trip;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function tripForFlightDetails(User $user): Trip
{
    return Trip::create([
        'user_id' => $user->id,
        'name' => 'Tokyo',
        'destination' => 'Tokyo, Japan',
        'starts_on' => '2026-10-03',
        'ends_on' => '2026-10-12',
        'status' => 'planned',
    ]);
}

function flightReservationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'flight',
        'title' => 'Outbound flight',
        'provider_name' => 'United',
        'booking_reference' => 'ABC123',
        'status' => 'confirmed',
        'starts_at' => '2026-10-03T08:30',
        'starts_timezone' => 'America/Denver',
        'ends_at' => '2026-10-04T14:10',
        'ends_timezone' => 'Asia/Tokyo',
        'airline' => 'United',
        'flight_number' => 'UA143',
        'departure_airport' => 'DEN',
        'arrival_airport' => 'HND',
    ], $overrides);
}

function lodgingReservationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'lodging',
        'title' => 'Shinjuku hotel',
        'provider_name' => 'Hotel Century',
        'booking_reference' => 'HOTEL123',
        'status' => 'confirmed',
        'starts_at' => '2026-10-04T15:00',
        'starts_timezone' => 'Asia/Tokyo',
        'ends_at' => '2026-10-12T11:00',
        'ends_timezone' => 'Asia/Tokyo',
        'property_name' => 'Hotel Century',
        'room_type' => 'King',
    ], $overrides);
}

function emptyFlightDetails(): array
{
    return [
        'cabin_class' => null,
        'currency' => null,
        'carry_on_size' => null,
        'carry_on_weight' => null,
        'carry_on_fee' => null,
        'personal_item_size' => null,
        'personal_item_weight' => null,
        'personal_item_fee' => null,
        'checked_bag_size' => null,
        'checked_bag_weight' => null,
        'checked_bag_fee' => null,
        'additional_checked_bag_fee' => null,
        'additional_checked_bag_allowance' => null,
        'visa_requirement' => null,
        'passport_validity_rule' => null,
        'layover_notes' => null,
        'online_check_in_opens' => null,
        'boarding_closes' => null,
        'notes' => null,
    ];
}

test('it does not create flight details when every detail value is empty', function () {
    $user = User::factory()->create();
    $trip = tripForFlightDetails($user);

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), flightReservationPayload([
            'flight_details' => emptyFlightDetails(),
        ]))
        ->assertRedirect();

    expect($trip->reservations()->first()->flightDetails)->toBeNull();
});

test('it creates updates and deletes flight details lazily', function () {
    $user = User::factory()->create();
    $trip = tripForFlightDetails($user);

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), flightReservationPayload([
            'flight_details' => ['cabin_class' => 'economy', 'currency' => 'USD'],
        ]))
        ->assertRedirect();

    $reservation = $trip->reservations()->first();

    expect($reservation->flightDetails)
        ->not->toBeNull()
        ->and($reservation->flightDetails->cabin_class)->toBe('economy');

    $this->actingAs($user)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), flightReservationPayload([
            'flight_details' => [
                'cabin_class' => 'business',
                'currency' => 'USD',
                'carry_on_size' => '55x40x20 cm',
            ],
        ]))
        ->assertRedirect();

    expect($reservation->flightDetails()->count())->toBe(1)
        ->and($reservation->fresh()->flightDetails->cabin_class)->toBe('business')
        ->and($reservation->fresh()->flightDetails->carry_on_size)->toBe('55x40x20 cm');

    $this->actingAs($user)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), flightReservationPayload([
            'flight_details' => emptyFlightDetails(),
        ]))
        ->assertRedirect();

    expect($reservation->fresh()->flightDetails)->toBeNull();
});

test('it deletes flight details when changing away from flight and ignores details for non flights', function () {
    $user = User::factory()->create();
    $trip = tripForFlightDetails($user);

    $reservation = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'Outbound flight',
        'status' => 'confirmed',
        'starts_timezone' => 'America/Denver',
        'ends_timezone' => 'Asia/Tokyo',
    ]);
    $reservation->flightDetails()->create(['cabin_class' => 'economy', 'currency' => 'USD']);

    $this->actingAs($user)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), lodgingReservationPayload())
        ->assertRedirect();

    expect($reservation->fresh()->flightDetails)->toBeNull()
        ->and($reservation->fresh()->lodgingStay)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), lodgingReservationPayload([
            'flight_details' => ['cabin_class' => 'first', 'currency' => 'USD'],
        ]))
        ->assertRedirect();

    expect($trip->reservations()->latest('id')->first()->flightDetails)->toBeNull();
});

test('it validates flight details fields', function (array $flightDetails, string $errorKey) {
    $user = User::factory()->create();
    $trip = tripForFlightDetails($user);

    $this->actingAs($user)
        ->from(route('trips.show', $trip))
        ->post(route('trips.reservations.store', $trip), flightReservationPayload([
            'flight_details' => $flightDetails,
        ]))
        ->assertRedirect(route('trips.show', $trip))
        ->assertSessionHasErrors($errorKey);
})->with([
    'invalid cabin' => [['cabin_class' => 'wagon'], 'flight_details.cabin_class'],
    'short currency' => [['currency' => 'US'], 'flight_details.currency'],
    'lowercase currency' => [['currency' => 'usd'], 'flight_details.currency'],
    'negative fee' => [['carry_on_fee' => '-1'], 'flight_details.carry_on_fee'],
    'too many decimals' => [['carry_on_fee' => '12.345'], 'flight_details.carry_on_fee'],
    'oversized visa' => [['visa_requirement' => str_repeat('a', 1001)], 'flight_details.visa_requirement'],
    'oversized layover' => [['layover_notes' => str_repeat('a', 1001)], 'flight_details.layover_notes'],
    'oversized notes' => [['notes' => str_repeat('a', 1001)], 'flight_details.notes'],
    'oversized check in' => [['online_check_in_opens' => str_repeat('a', 81)], 'flight_details.online_check_in_opens'],
    'oversized boarding' => [['boarding_closes' => str_repeat('a', 81)], 'flight_details.boarding_closes'],
]);

test('it accepts a fully populated flight details payload', function () {
    $user = User::factory()->create();
    $trip = tripForFlightDetails($user);
    $details = [
        'cabin_class' => 'premium_economy',
        'currency' => 'EUR',
        'carry_on_size' => '55x40x20 cm',
        'carry_on_weight' => '7 kg',
        'carry_on_fee' => '25.00',
        'personal_item_size' => '40x30x15 cm',
        'personal_item_weight' => '2 kg',
        'personal_item_fee' => '0.00',
        'checked_bag_size' => '158 cm total',
        'checked_bag_weight' => '23 kg',
        'checked_bag_fee' => '55.50',
        'additional_checked_bag_fee' => '90.00',
        'additional_checked_bag_allowance' => 'Up to 2 extra bags',
        'visa_requirement' => 'ETA required before boarding.',
        'passport_validity_rule' => 'Valid for six months after arrival.',
        'layover_notes' => 'Terminal change at FRA.',
        'online_check_in_opens' => '24h before departure',
        'boarding_closes' => '30 min before departure',
        'notes' => 'Ask about lounge access.',
    ];

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), flightReservationPayload([
            'flight_details' => $details,
        ]))
        ->assertRedirect();

    $flightDetails = $trip->reservations()->first()->flightDetails;

    expect($flightDetails->cabin_class)->toBe('premium_economy')
        ->and($flightDetails->currency)->toBe('EUR')
        ->and($flightDetails->carry_on_fee)->toBe('25.00')
        ->and($flightDetails->layover_notes)->toBe('Terminal change at FRA.')
        ->and($flightDetails->online_check_in_opens)->toBe('24h before departure')
        ->and($flightDetails->boarding_closes)->toBe('30 min before departure');
});

test('it suggests currency from user preference then costs then usd', function () {
    $user = User::factory()->create();
    $trip = tripForFlightDetails($user);

    $user->travelPreference()->create(['default_currency' => 'GBP']);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn (Assert $page) => $page->where('trip.suggested_currency', 'GBP'));

    $user->travelPreference()->delete();
    $trip->costs()->create(['category' => 'flight', 'label' => 'Deposit', 'currency' => 'USD']);
    $trip->costs()->create(['category' => 'lodging', 'label' => 'Hotel', 'currency' => 'EUR']);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn (Assert $page) => $page->where('trip.suggested_currency', 'EUR'));

    $trip->costs()->delete();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn (Assert $page) => $page->where('trip.suggested_currency', 'USD'));
});

test('it enforces flight details authorization and trip boundaries', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = tripForFlightDetails($owner);
    $otherTrip = tripForFlightDetails($owner);
    $reservation = $otherTrip->reservations()->create([
        'type' => 'flight',
        'title' => 'Other trip flight',
        'status' => 'confirmed',
        'starts_timezone' => 'UTC',
        'ends_timezone' => 'UTC',
    ]);

    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), flightReservationPayload([
            'flight_details' => ['cabin_class' => 'economy'],
        ]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), flightReservationPayload([
            'flight_details' => ['cabin_class' => 'economy'],
        ]))
        ->assertNotFound();

    $this->post(route('trips.reservations.store', $trip), flightReservationPayload())
        ->assertRedirect(route('home'));
});

test('it includes flight details in the trip payload and json export', function () {
    $user = User::factory()->create();
    $trip = tripForFlightDetails($user);
    $reservation = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'Outbound flight',
        'status' => 'confirmed',
        'starts_timezone' => 'America/Denver',
        'ends_timezone' => 'Asia/Tokyo',
    ]);
    $reservation->flightDetails()->create([
        'cabin_class' => 'economy',
        'currency' => 'USD',
        'layover_notes' => 'Short connection.',
        'online_check_in_opens' => '24h before',
        'boarding_closes' => '30 min before',
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trip.reservations.0.flight_details.cabin_class', 'economy')
            ->where('trip.reservations.0.flight_details.layover_notes', 'Short connection.'),
        );

    $this->actingAs($user)
        ->get(route('trips.export.json', $trip))
        ->assertOk()
        ->assertJsonPath('reservations.0.flight_details.cabin_class', 'economy')
        ->assertJsonPath('reservations.0.flight_details.online_check_in_opens', '24h before');
});
