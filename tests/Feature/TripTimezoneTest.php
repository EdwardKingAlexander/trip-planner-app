<?php

use App\Models\Trip;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create a trip with destination and home timezones', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('trips.store'), [
        'name' => 'Manila anniversary',
        'destination' => 'Manila, Philippines',
        'destination_timezone' => 'Asia/Manila',
        'home_timezone' => 'America/Los_Angeles',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ])->assertRedirect();

    $trip = Trip::firstOrFail();

    expect($trip->destination_timezone)->toBe('Asia/Manila')
        ->and($trip->home_timezone)->toBe('America/Los_Angeles');
});

test('trip validation rejects invalid timezone identifiers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('trips.store'), [
        'name' => 'Mars trip',
        'destination' => 'Olympus Mons',
        'destination_timezone' => 'Mars/Olympus_Mons',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ])->assertSessionHasErrors('destination_timezone');
});

test('known destinations are inferred when timezone is not submitted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('trips.store'), [
        'name' => 'Tokyo food week',
        'destination' => 'Tokyo, Japan',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ])->assertRedirect();

    expect(Trip::firstOrFail()->destination_timezone)->toBe('Asia/Tokyo');
});

test('trip payload includes timezone fields and an editable details surface', function () {
    $user = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Unknown place',
        'destination' => 'Some Tiny Town',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ]);
    $trip->syncDays();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Trips/Show')
            ->where('trip.destination_timezone', null)
            ->where('trip.effective_destination_timezone', 'UTC')
            ->has('timezones')
        );
});
