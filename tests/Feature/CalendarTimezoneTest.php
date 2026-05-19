<?php

use App\Models\Trip;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('calendar includes trip all day events on the destination local date', function () {
    $user = User::factory()->create();
    $user->travelPreference()->create(['home_timezone' => 'America/Los_Angeles']);
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Manila',
        'destination' => 'Manila, Philippines',
        'destination_timezone' => 'Asia/Manila',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
        'status' => 'planned',
    ]);

    $this->actingAs($user)
        ->get(route('calendar.index', ['month' => '2026-09-01']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/Index')
            ->where('events.0.id', "trip-{$trip->id}")
            ->where('events.0.startsAt', '2026-09-26')
            ->where('events.0.timezone', 'Asia/Manila')
        );
});
