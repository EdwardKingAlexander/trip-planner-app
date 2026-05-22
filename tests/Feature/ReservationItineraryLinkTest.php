<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function itineraryLinkTrip(array $overrides = []): array
{
    $user = User::factory()->create();
    $trip = Trip::create(array_merge([
        'user_id' => $user->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'destination_timezone' => 'Europe/Lisbon',
        'home_timezone' => 'America/Denver',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-15',
        'status' => 'planned',
    ], $overrides));
    $trip->syncDays();

    return [$user, $trip];
}

function reservationItineraryPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'activity',
        'title' => 'Fado dinner',
        'provider_name' => 'Clube de Fado',
        'booking_reference' => 'FADO123',
        'status' => 'confirmed',
        'starts_at' => '2026-09-11T19:00',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_at' => '2026-09-11T21:00',
        'ends_timezone' => 'Europe/Lisbon',
        'location_name' => 'Alfama',
    ], $overrides);
}

function pivotDates($reservation): array
{
    return $reservation->tripDays()
        ->pluck('date')
        ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
        ->all();
}

test('creating a reservation populates trip day pivot rows', function () {
    [$user, $trip] = itineraryLinkTrip();

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), reservationItineraryPayload([
            'type' => 'lodging',
            'title' => 'Alfama hotel',
            'starts_at' => '2026-09-11T15:00',
            'ends_at' => '2026-09-14T11:00',
            'property_name' => 'Alfama hotel',
        ]))
        ->assertRedirect();

    $reservation = $trip->reservations()->firstOrFail();

    expect(pivotDates($reservation))
        ->toBe(['2026-09-11', '2026-09-12', '2026-09-13']);
});

test('updating a reservation rewrites the pivot span', function () {
    [$user, $trip] = itineraryLinkTrip();

    $reservation = $trip->reservations()->create([
        'type' => 'activity',
        'title' => 'Museum pass',
        'status' => 'confirmed',
        'starts_at' => '2026-09-11 10:00:00',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_at' => '2026-09-11 12:00:00',
        'ends_timezone' => 'Europe/Lisbon',
    ]);
    $reservation->tripDays()->sync([$trip->days()->where('date', '2026-09-11')->firstOrFail()->id]);

    $this->actingAs($user)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), reservationItineraryPayload([
            'type' => 'transport',
            'title' => 'Porto train',
            'starts_at' => '2026-09-12T09:00',
            'ends_at' => '2026-09-13T13:00',
        ]))
        ->assertRedirect();

    expect(pivotDates($reservation->fresh()))
        ->toBe(['2026-09-12', '2026-09-13']);
});

test('changing a multi day reservation to lodging applies checkout exclusion', function () {
    [$user, $trip] = itineraryLinkTrip();

    $reservation = $trip->reservations()->create([
        'type' => 'transport',
        'title' => 'Road trip',
        'status' => 'confirmed',
        'starts_at' => '2026-09-11 15:00:00',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_at' => '2026-09-14 11:00:00',
        'ends_timezone' => 'Europe/Lisbon',
    ]);

    $this->actingAs($user)
        ->patch(route('trips.reservations.update', [$trip, $reservation]), reservationItineraryPayload([
            'type' => 'lodging',
            'title' => 'Alfama hotel',
            'starts_at' => '2026-09-11T15:00',
            'ends_at' => '2026-09-14T11:00',
            'property_name' => 'Alfama hotel',
        ]))
        ->assertRedirect();

    expect(pivotDates($reservation->fresh()))
        ->toBe(['2026-09-11', '2026-09-12', '2026-09-13']);
});

test('deleting a reservation cascades pivot rows', function () {
    [$user, $trip] = itineraryLinkTrip();
    $day = $trip->days()->where('date', '2026-09-11')->firstOrFail();
    $reservation = $trip->reservations()->create([
        'type' => 'activity',
        'title' => 'Museum pass',
        'status' => 'confirmed',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_timezone' => 'Europe/Lisbon',
    ]);
    $reservation->tripDays()->sync([$day->id]);

    $this->actingAs($user)
        ->delete(route('trips.reservations.destroy', [$trip, $reservation]))
        ->assertRedirect();

    $this->assertDatabaseMissing('trip_day_reservation', [
        'trip_day_id' => $day->id,
        'reservation_id' => $reservation->id,
    ]);
});

test('unscheduled reservations do not create pivot rows', function () {
    [$user, $trip] = itineraryLinkTrip();

    $this->actingAs($user)
        ->post(route('trips.reservations.store', $trip), reservationItineraryPayload([
            'title' => 'Unscheduled booking',
            'starts_at' => '',
            'ends_at' => '',
        ]))
        ->assertRedirect();

    expect($trip->reservations()->firstOrFail()->tripDays)->toHaveCount(0);
});

test('trip show payload exposes per day reservation cards', function () {
    [$user, $trip] = itineraryLinkTrip();
    $hotel = $trip->reservations()->create([
        'type' => 'lodging',
        'title' => 'Alfama hotel',
        'provider_name' => 'Harbor House',
        'status' => 'confirmed',
        'starts_at' => '2026-09-11 15:00:00',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_at' => '2026-09-14 11:00:00',
        'ends_timezone' => 'Europe/Lisbon',
        'location_name' => 'Alfama',
    ]);
    $earlyFlight = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'Morning flight',
        'provider_name' => 'TAP',
        'status' => 'confirmed',
        'starts_at' => '2026-09-12 08:00:00',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_at' => '2026-09-12 10:00:00',
        'ends_timezone' => 'Europe/Lisbon',
    ]);
    $lateFlight = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'Evening flight',
        'provider_name' => 'TAP',
        'status' => 'confirmed',
        'starts_at' => '2026-09-12 18:00:00',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_at' => '2026-09-12 20:00:00',
        'ends_timezone' => 'Europe/Lisbon',
    ]);
    $unscheduled = $trip->reservations()->create([
        'type' => 'custom',
        'title' => 'Unscheduled',
        'status' => 'reserved',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_timezone' => 'Europe/Lisbon',
    ]);

    foreach ([$hotel, $earlyFlight, $lateFlight, $unscheduled] as $reservation) {
        $dates = $reservation->computeOverlappingDates($trip);
        $reservation->tripDays()->sync(
            $trip->days()->whereIn('date', array_map(fn ($date) => $date->toDateString(), $dates))->pluck('id')->all(),
        );
    }

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Trips/Show')
            ->where('trip.days.1.reservations.0.id', $hotel->id)
            ->where('trip.days.1.reservations.0.day_index', 1)
            ->where('trip.days.1.reservations.0.day_total', 3)
            ->where('trip.days.1.reservations.0.spans_multiple_days', true)
            ->where('trip.days.2.reservations.0.id', $hotel->id)
            ->where('trip.days.2.reservations.1.id', $earlyFlight->id)
            ->where('trip.days.2.reservations.2.id', $lateFlight->id)
            ->where('trip.days.4.reservations', []),
        );

    expect($unscheduled->fresh()->tripDays)->toHaveCount(0);
});
