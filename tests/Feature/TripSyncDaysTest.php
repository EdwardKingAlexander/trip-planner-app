<?php

use App\Models\Trip;
use App\Models\User;

test('sync days labels same timezone trips as vacation days', function () {
    $trip = Trip::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'San Francisco',
        'destination' => 'San Francisco, California',
        'destination_timezone' => 'America/Los_Angeles',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-03',
        'status' => 'planned',
    ]);

    $trip->syncDays();
    $trip->load('days');

    expect($trip->days)->toHaveCount(3)
        ->and($trip->days->pluck('kind')->all())->toBe(['vacation', 'vacation', 'vacation'])
        ->and($trip->dayLabelFor($trip->days[0]))->toBe('Day 1');
});

test('sync days marks cross timezone outbound travel and arrival from flight segments', function () {
    $trip = Trip::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Manila',
        'destination' => 'Manila, Philippines',
        'destination_timezone' => 'Asia/Manila',
        'home_timezone' => 'America/Los_Angeles',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-09-28',
        'status' => 'planned',
    ]);
    $reservation = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'LAX to Manila',
        'starts_at' => '2026-09-27 05:00:00',
        'starts_timezone' => 'America/Los_Angeles',
        'ends_at' => '2026-09-27 10:00:00',
        'ends_timezone' => 'Asia/Manila',
    ]);
    $reservation->flightSegments()->create([
        'departure_airport' => 'LAX',
        'arrival_airport' => 'MNL',
        'departs_at' => '2026-09-26 22:00:00',
        'departure_timezone' => 'America/Los_Angeles',
        'arrives_at' => '2026-09-27 10:00:00',
        'arrival_timezone' => 'Asia/Manila',
    ]);

    $trip->syncDays();
    $trip->load('days');

    expect($trip->days->pluck('date')->map->toDateString()->all())->toBe(['2026-09-26', '2026-09-27', '2026-09-28'])
        ->and($trip->days->pluck('kind')->all())->toBe(['travel-out', 'arrival', 'vacation'])
        ->and($trip->dayLabelFor($trip->days[0]))->toBe('Travel · departure · Day 1')
        ->and($trip->dayLabelFor($trip->days[1]))->toBe('Arrival · Day 2');
});

test('day one is always the first planned trip date', function () {
    $trip = Trip::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'London',
        'destination' => 'London, UK',
        'destination_timezone' => 'Europe/London',
        'starts_on' => '2026-10-24',
        'ends_on' => '2026-10-27',
        'status' => 'planned',
    ]);
    $trip->syncDays();
    $trip->days()->whereDate('date', '2026-10-26')->firstOrFail()->forceFill([
        'is_day_one_anchor' => true,
    ])->save();

    $trip->refresh()->syncDays();
    $trip->load('days');

    expect($trip->days()->where('is_day_one_anchor', true)->count())->toBe(1)
        ->and($trip->days[0]->is_day_one_anchor)->toBeTrue()
        ->and($trip->days[0]->date->toDateString())->toBe('2026-10-24')
        ->and($trip->dayLabelFor($trip->days[0]))->toBe('Day 1')
        ->and($trip->dayLabelFor($trip->days[2]))->toBe('Day 3');
});
