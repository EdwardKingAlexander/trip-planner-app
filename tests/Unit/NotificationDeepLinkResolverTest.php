<?php

use App\Models\Trip;
use App\Models\TripActivityEvent;
use App\Models\User;
use App\Notifications\TripChangedNotification;
use App\Services\NotificationDeepLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function resolverTrip(): Trip
{
    $trip = Trip::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);

    $trip->syncDays();

    return $trip;
}

function notificationForSubject(Trip $trip, ?object $subject, string $changedArea = 'packing')
{
    $user = User::factory()->create();
    $event = TripActivityEvent::create([
        'trip_id' => $trip->id,
        'user_id' => $trip->user_id,
        'event_type' => $changedArea.'.updated',
        'changed_area' => $changedArea,
        'subject_type' => $subject?->getMorphClass(),
        'subject_id' => $subject?->getKey(),
        'summary' => 'updated item',
        'metadata' => ['trip_name' => $trip->name],
    ]);

    $user->notify(new TripChangedNotification($event));

    return $user->notifications()->first();
}

test('resolves every notification subject type to its trip panel and anchor', function (string $panel, string $anchorPrefix, callable $factory) {
    $trip = resolverTrip();
    $subject = $factory($trip);
    $notification = notificationForSubject($trip, $subject, $panel);

    $url = app(NotificationDeepLinkResolver::class)($notification);

    expect($url)->toBe("/trips/{$trip->id}?focus={$panel}&from=notification#{$anchorPrefix}-{$subject->id}");
})->with([
    'itinerary' => ['itinerary', 'itinerary-item', fn (Trip $trip) => $trip->itineraryItems()->create([
        'trip_day_id' => $trip->days()->first()->id,
        'type' => 'activity',
        'title' => 'Walking tour',
        'timezone' => 'Europe/Lisbon',
        'status' => 'planned',
    ])],
    'reservation' => ['reservations', 'reservation', fn (Trip $trip) => $trip->reservations()->create([
        'type' => 'lodging',
        'title' => 'Hotel',
        'status' => 'reserved',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_timezone' => 'Europe/Lisbon',
    ])],
    'cost' => ['budget', 'cost', fn (Trip $trip) => $trip->costs()->create([
        'category' => 'lodging',
        'label' => 'Hotel',
        'currency' => 'USD',
    ])],
    'packing' => ['packing', 'packing-item', fn (Trip $trip) => $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ])],
    'task' => ['tasks', 'task', fn (Trip $trip) => $trip->tasks()->create([
        'title' => 'Check passport',
        'priority' => 'normal',
    ])],
    'document' => ['documents', 'document', fn (Trip $trip) => $trip->documents()->create([
        'title' => 'Passport',
        'document_type' => 'identity',
    ])],
    'reminder' => ['reminders', 'reminder', fn (Trip $trip) => $trip->reminders()->create([
        'label' => 'Check in',
        'remind_at' => '2026-09-09 09:00:00',
        'timezone' => 'America/Denver',
        'delivery_channels' => ['in_app'],
    ])],
]);

test('resolves trip-level notifications to the trip overview', function () {
    $trip = resolverTrip();
    $notification = notificationForSubject($trip, null);

    expect(app(NotificationDeepLinkResolver::class)($notification))->toBe("/trips/{$trip->id}?from=notification");
});

test('resolves stale subjects to the panel with a missing marker', function () {
    $trip = resolverTrip();
    $item = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
    ]);
    $notification = notificationForSubject($trip, $item);

    $item->delete();

    expect(app(NotificationDeepLinkResolver::class)($notification))->toBe("/trips/{$trip->id}?focus=packing&from=notification&missing=1");
});

test('resolves unknown subject types to the trip overview', function () {
    $trip = resolverTrip();
    $notification = notificationForSubject($trip, null);
    $data = $notification->data;
    $data['subject_type'] = 'App\\Models\\UnknownThing';
    $data['subject_id'] = 123;
    $notification->update(['data' => $data]);

    expect(app(NotificationDeepLinkResolver::class)($notification->fresh()))->toBe("/trips/{$trip->id}?from=notification");
});
