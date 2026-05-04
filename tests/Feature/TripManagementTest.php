<?php

use App\Models\Trip;
use App\Models\User;

test('users can create a trip with generated trip days', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('trips.store'), [
        'name' => 'Anniversary in Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
        'summary' => 'Food, views, and slow mornings.',
        'cover_theme' => 'coastal',
    ]);

    $trip = Trip::first();

    $response->assertRedirect(route('trips.show', $trip));
    expect($trip)->not->toBeNull()
        ->and($trip->fresh()->days)->toHaveCount(3);
});

test('trip owners can share trips with an editor', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Oahu',
        'destination' => 'Honolulu, Hawaii',
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-08',
        'status' => 'planned',
    ]);

    $this->actingAs($owner)->post(route('trips.collaborators.store', $trip), [
        'email' => $editor->email,
        'role' => 'editor',
    ])->assertRedirect();

    expect($trip->fresh()->collaborators)->toHaveCount(1);

    $this->actingAs($editor)
        ->get(route('trips.show', $trip), ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->assertOk();
});

test('non collaborators cannot view another users trip', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Paris',
        'destination' => 'Paris, France',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
        'status' => 'planned',
    ]);

    $this->actingAs($stranger)->get(route('trips.show', $trip))->assertForbidden();
});

test('editors can add reservations with local date time details', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Tokyo',
        'destination' => 'Tokyo, Japan',
        'starts_on' => '2026-10-03',
        'ends_on' => '2026-10-12',
        'status' => 'planned',
    ]);
    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);

    $this->actingAs($editor)->post(route('trips.reservations.store', $trip), [
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
    ])->assertRedirect();

    $reservation = $trip->reservations()->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->starts_timezone)->toBe('America/Denver')
        ->and($reservation->ends_timezone)->toBe('Asia/Tokyo')
        ->and($reservation->flightSegments)->toHaveCount(1);
});
