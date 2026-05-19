<?php

use App\Models\Trip;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('calendar requires authentication', function () {
    $this->get(route('calendar.index'))->assertRedirect(route('login'));
});

test('calendar shows only visible trip events for the requested month', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Denver Weekend',
        'destination' => 'Denver, Colorado',
        'starts_on' => '2026-06-10',
        'ends_on' => '2026-06-12',
        'status' => 'planned',
    ]);
    $trip->syncDays();

    $trip->itineraryItems()->create([
        'trip_day_id' => $trip->days()->first()->id,
        'type' => 'activity',
        'title' => 'Botanic Gardens',
        'starts_at' => '2026-06-10 14:00:00',
        'ends_at' => '2026-06-10 16:00:00',
        'timezone' => 'America/Denver',
        'status' => 'planned',
        'sort_order' => 0,
    ]);

    $trip->reservations()->create([
        'type' => 'lodging',
        'title' => 'Union Station hotel',
        'status' => 'confirmed',
        'starts_at' => '2026-06-10 15:00:00',
        'starts_timezone' => 'America/Denver',
        'ends_timezone' => 'America/Denver',
    ]);

    $trip->tasks()->create([
        'title' => 'Confirm timed entry',
        'description' => 'Check the reservation window.',
        'due_at' => '2026-06-11 09:00:00',
        'priority' => 'high',
    ]);

    $otherTrip = Trip::create([
        'user_id' => $stranger->id,
        'name' => 'Private Trip',
        'destination' => 'Hidden',
        'starts_on' => '2026-06-10',
        'ends_on' => '2026-06-11',
        'status' => 'planned',
    ]);
    $otherTrip->reminders()->create([
        'label' => 'Should not leak',
        'remind_at' => '2026-06-10 12:00:00',
        'timezone' => 'America/Denver',
        'delivery_channels' => ['in_app'],
    ]);

    $this->actingAs($owner)
        ->get(route('calendar.index', ['month' => '2026-06-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/Index')
            ->where('month', '2026-06-01')
            ->has('events', 4)
            ->where('events.0.kind', 'trip')
            ->where('events.0.title', 'Denver Weekend')
            ->where('events.1.title', 'Botanic Gardens')
            ->where('events.2.title', 'Union Station hotel')
            ->where('events.3.kind', 'task')
            ->where('events.3.title', 'Confirm timed entry'),
        );
});
