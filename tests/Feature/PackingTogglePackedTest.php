<?php

use App\Models\Trip;
use App\Models\User;

function makePackingTrip(User $owner, ?User $editor = null): Trip
{
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);

    if ($editor !== null) {
        $trip->collaborators()->create([
            'user_id' => $editor->id,
            'email' => $editor->email,
            'role' => 'editor',
            'accepted_at' => now(),
        ]);
    }

    return $trip;
}

test('trip owners can toggle a packing item packed', function () {
    $owner = User::factory()->create();
    $trip = makePackingTrip($owner);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
        'is_packed' => false,
    ]);

    $this->actingAs($owner)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertRedirect();

    expect($item->fresh()->is_packed)->toBeTrue()
        ->and($item->fresh()->updated_by_user_id)->toBe($owner->id)
        ->and($trip->activityEvents()->where('event_type', 'packing.toggled')->where('summary', 'marked packed: Jacket')->exists())->toBeTrue();
});

test('editors can toggle a packing item back to unpacked', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = makePackingTrip($owner, $editor);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
        'is_packed' => true,
    ]);

    $this->actingAs($editor)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => false])
        ->assertRedirect();

    expect($item->fresh()->is_packed)->toBeFalse()
        ->and($item->fresh()->updated_by_user_id)->toBe($editor->id)
        ->and($trip->activityEvents()->where('event_type', 'packing.toggled')->where('summary', 'marked unpacked: Jacket')->exists())->toBeTrue();
});

test('toggle rejects a non boolean value', function () {
    $owner = User::factory()->create();
    $trip = makePackingTrip($owner);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => 'maybe'])
        ->assertUnprocessable();
});

test('toggle rejects viewers and strangers', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = makePackingTrip($owner);
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ]);

    $this->actingAs($viewer)->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])->assertForbidden();
    $this->actingAs($stranger)->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])->assertForbidden();
});

test('toggle returns not found when the item belongs to another trip', function () {
    $owner = User::factory()->create();
    $trip = makePackingTrip($owner);
    $otherTrip = makePackingTrip($owner);
    $item = $otherTrip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ]);

    $this->actingAs($owner)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertNotFound();
});

test('toggle requires authentication', function () {
    $owner = User::factory()->create();
    $trip = makePackingTrip($owner);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ]);

    $this->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertRedirect(route('login'));
});
