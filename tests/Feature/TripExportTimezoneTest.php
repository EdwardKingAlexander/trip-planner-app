<?php

use App\Models\Trip;
use App\Models\User;

test('ics export emits the trip date as written with destination timezone metadata', function () {
    $user = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Manila',
        'destination' => 'Manila, Philippines',
        'destination_timezone' => 'Asia/Manila',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ]);

    $response = $this->actingAs($user)->get(route('trips.export.ics', $trip));

    $response->assertOk()
        ->assertSee('X-WR-TIMEZONE:Asia/Manila', false)
        ->assertSee('DTSTART;VALUE=DATE:20260926', false)
        ->assertSee('DTEND;VALUE=DATE:20261006', false);
});

test('json export includes timezone and day label metadata', function () {
    $user = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'London',
        'destination' => 'London, UK',
        'destination_timezone' => 'Europe/London',
        'starts_on' => '2026-10-24',
        'ends_on' => '2026-10-25',
        'status' => 'planned',
    ]);
    $trip->syncDays();

    $response = $this->actingAs($user)->getJson(route('trips.export.json', $trip));

    $response->assertOk()
        ->assertJsonPath('trip.destination_timezone', 'Europe/London')
        ->assertJsonPath('days.0.kind', 'vacation')
        ->assertJsonPath('days.0.label', 'Day 1');
});
