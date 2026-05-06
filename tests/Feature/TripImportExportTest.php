<?php

use App\Models\Trip;
use App\Models\User;

function tripFor(User $user): Trip
{
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Kyoto Spring',
        'destination' => 'Kyoto, Japan',
        'starts_on' => '2026-04-01',
        'ends_on' => '2026-04-05',
        'status' => 'planned',
    ]);

    $trip->syncDays('2026-04-01', '2026-04-05');

    return $trip;
}

test('editors can parse and commit an ics import', function () {
    $user = User::factory()->create();
    $trip = tripFor($user);

    $ics = implode("\r\n", [
        'BEGIN:VCALENDAR',
        'BEGIN:VEVENT',
        'SUMMARY:Tea ceremony',
        'DTSTART:20260402T140000Z',
        'DTEND:20260402T153000Z',
        'LOCATION:Gion',
        'END:VEVENT',
        'END:VCALENDAR',
    ]);

    $this->actingAs($user)->post(route('trips.imports.store', $trip), [
        'source_type' => 'ics',
        'raw_text' => $ics,
    ])->assertRedirect();

    $batch = $trip->importBatches()->first();
    expect($batch->parsed_payload['items'])->toHaveCount(1);

    $this->actingAs($user)->post(route('trips.imports.commit', [$trip, $batch]))->assertRedirect();

    expect($trip->itineraryItems()->where('title', 'Tea ceremony')->exists())->toBeTrue()
        ->and($batch->fresh()->status)->toBe('committed');
});

test('confirmation text imports create reservations after review', function () {
    $user = User::factory()->create();
    $trip = tripFor($user);

    $this->actingAs($user)->post(route('trips.imports.store', $trip), [
        'source_type' => 'confirmation',
        'raw_text' => "Hotel: Park Stay\nConfirmation: ABC123\n2026-04-01 15:00\n2026-04-05 11:00",
    ])->assertRedirect();

    $batch = $trip->importBatches()->first();

    $this->actingAs($user)->post(route('trips.imports.commit', [$trip, $batch]))->assertRedirect();

    expect($trip->reservations()->where('booking_reference', 'ABC123')->exists())->toBeTrue();
});

test('trip calendar and json exports are available to collaborators', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = tripFor($owner);
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);
    $trip->itineraryItems()->create([
        'type' => 'activity',
        'title' => 'Temple walk',
        'starts_at' => '2026-04-03 09:00:00',
        'ends_at' => '2026-04-03 10:30:00',
        'timezone' => 'Asia/Tokyo',
        'status' => 'planned',
    ]);

    $this->actingAs($viewer)->get(route('trips.export.ics', $trip))
        ->assertOk()
        ->assertSee('BEGIN:VCALENDAR');

    $this->actingAs($viewer)->get(route('trips.export.json', $trip))
        ->assertOk()
        ->assertJsonPath('trip.name', 'Kyoto Spring');
});

test('global search finds visible trip details', function () {
    $user = User::factory()->create();
    $trip = tripFor($user);
    $trip->documents()->create([
        'title' => 'Passport scan',
        'document_type' => 'id',
    ]);

    $this->actingAs($user)
        ->get(route('trips.search', ['q' => 'Passport']), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonFragment(['query' => 'Passport']);
});

test('users can update travel preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('travel-preferences.update'), [
        'home_timezone' => 'America/Denver',
        'default_currency' => 'USD',
        'traveler_profiles_text' => "Alex\nEmma",
        'packing_templates_text' => "Passport\nChargers",
    ])->assertRedirect();

    $preference = $user->travelPreference()->first();

    expect($preference->home_timezone)->toBe('America/Denver')
        ->and($preference->traveler_profiles)->toBe(['Alex', 'Emma']);
});
