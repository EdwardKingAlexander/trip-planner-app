<?php

use App\Models\Reservation;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function dateSpanTrip(array $overrides = []): Trip
{
    $trip = Trip::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'name' => 'Manila',
        'destination' => 'Manila, Philippines',
        'destination_timezone' => 'Asia/Manila',
        'home_timezone' => 'America/Los_Angeles',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ], $overrides));

    $trip->syncDays();

    return $trip;
}

function reservationDates(Reservation $reservation, Trip $trip): array
{
    return array_map(
        fn ($date) => $date->toDateString(),
        $reservation->computeOverlappingDates($trip),
    );
}

test('same day reservations overlap one trip day', function () {
    $trip = dateSpanTrip([
        'destination' => 'Los Angeles, California',
        'destination_timezone' => 'America/Los_Angeles',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-09-28',
    ]);

    $reservation = $trip->reservations()->create([
        'type' => 'activity',
        'title' => 'Dinner',
        'status' => 'confirmed',
        'starts_at' => '2026-09-27 18:00:00',
        'starts_timezone' => 'America/Los_Angeles',
        'ends_at' => '2026-09-27 20:00:00',
        'ends_timezone' => 'America/Los_Angeles',
    ]);

    expect(reservationDates($reservation, $trip))->toBe(['2026-09-27']);
});

test('multi day non lodging reservations include every overlapped day', function () {
    $trip = dateSpanTrip();

    $reservation = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'LAX to Manila',
        'status' => 'confirmed',
        'starts_at' => '2026-09-26 22:00:00',
        'starts_timezone' => 'America/Los_Angeles',
        'ends_at' => '2026-09-28 06:00:00',
        'ends_timezone' => 'Asia/Manila',
    ]);

    expect(reservationDates($reservation, $trip))->toBe(['2026-09-27', '2026-09-28']);
});

test('multi night lodging excludes the checkout day', function () {
    $trip = dateSpanTrip([
        'destination' => 'Lisbon, Portugal',
        'destination_timezone' => 'Europe/Lisbon',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-15',
    ]);

    $reservation = $trip->reservations()->create([
        'type' => 'lodging',
        'title' => 'Alfama hotel',
        'status' => 'confirmed',
        'starts_at' => '2026-09-11 15:00:00',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_at' => '2026-09-14 11:00:00',
        'ends_timezone' => 'Europe/Lisbon',
    ]);

    expect(reservationDates($reservation, $trip))->toBe(['2026-09-11', '2026-09-12', '2026-09-13']);
});

test('reservations without a start time have no overlapping dates', function () {
    $trip = dateSpanTrip();

    $reservation = $trip->reservations()->create([
        'type' => 'custom',
        'title' => 'Unscheduled idea',
        'status' => 'reserved',
        'starts_timezone' => 'Asia/Manila',
        'ends_timezone' => 'Asia/Manila',
    ]);

    expect($reservation->computeOverlappingDates($trip))->toBe([]);
});

test('date spans fall back to the reservation timezone and clamp to the trip envelope', function () {
    $trip = dateSpanTrip([
        'destination_timezone' => null,
        'starts_on' => '2026-09-27',
        'ends_on' => '2026-09-28',
    ]);

    $reservation = $trip->reservations()->create([
        'type' => 'transport',
        'title' => 'Road trip',
        'status' => 'confirmed',
        'starts_at' => '2026-09-26 22:00:00',
        'starts_timezone' => 'America/Los_Angeles',
        'ends_at' => '2026-09-29 01:00:00',
        'ends_timezone' => 'America/Los_Angeles',
    ]);

    expect(reservationDates($reservation, $trip))->toBe(['2026-09-27', '2026-09-28']);
});
