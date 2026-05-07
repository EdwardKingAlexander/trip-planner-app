<?php

use App\Models\Trip;
use App\Models\TripActivityEvent;
use App\Models\User;
use App\Notifications\TripChangedNotification;

function makeNotificationTrip(User $owner, User $editor): Trip
{
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);

    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);

    return $trip;
}

function sendStoredTripNotification(User $recipient, Trip $trip, object $subject)
{
    $event = TripActivityEvent::create([
        'trip_id' => $trip->id,
        'user_id' => $subject->created_by_user_id,
        'event_type' => 'packing.created',
        'changed_area' => 'packing',
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
        'summary' => 'added packing item: Jacket',
        'metadata' => ['trip_name' => $trip->name],
    ]);

    $recipient->notify(new TripChangedNotification($event));

    return $recipient->notifications()->first();
}

test('go marks the users notification as read and redirects to the resolved deep link', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = makeNotificationTrip($owner, $editor);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
        'created_by_user_id' => $editor->id,
    ]);
    $notification = sendStoredTripNotification($owner, $trip, $item);

    $this->actingAs($owner)
        ->get(route('notifications.go', $notification->id))
        ->assertRedirect("/trips/{$trip->id}?focus=packing&from=notification#packing-item-{$item->id}");

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('go does not touch another users notification', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = makeNotificationTrip($owner, $editor);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ]);
    $notification = sendStoredTripNotification($owner, $trip, $item);

    $this->actingAs($stranger)->get(route('notifications.go', $notification->id))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('go preserves an existing read timestamp', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = makeNotificationTrip($owner, $editor);
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ]);
    $notification = sendStoredTripNotification($owner, $trip, $item);
    $notification->markAsRead();
    $readAt = $notification->fresh()->read_at;

    $this->actingAs($owner)->get(route('notifications.go', $notification->id))->assertRedirect();

    expect($notification->fresh()->read_at->eq($readAt))->toBeTrue();
});

test('go requires authentication', function () {
    $this->get(route('notifications.go', 'missing'))->assertRedirect(route('login'));
});
