<?php

use App\Models\Trip;
use App\Models\TripActivityEvent;
use App\Models\User;
use App\Notifications\TripChangedNotification;
use Illuminate\Support\Facades\Notification;

function makeSharedTrip(User $owner, ?User $editor = null): Trip
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

test('a mutation by a collaborator records an activity event and notifies the owner', function () {
    Notification::fake();

    $owner = User::factory()->create(['name' => 'Owen Owner']);
    $editor = User::factory()->create(['name' => 'Eddie Editor']);
    $trip = makeSharedTrip($owner, $editor);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $event = TripActivityEvent::where('trip_id', $trip->id)->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->changed_area)->toBe('tasks')
        ->and($event->event_type)->toBe('task.created')
        ->and($event->user_id)->toBe($editor->id);

    Notification::assertSentTo($owner, TripChangedNotification::class);
    Notification::assertNotSentTo($editor, TripChangedNotification::class);
});

test('the actor is excluded from their own notifications', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = makeSharedTrip($owner, $editor);

    $this->actingAs($owner)->post(route('trips.costs.store', $trip), [
        'category' => 'food',
        'label' => 'Dinner',
        'planned_amount' => '60',
        'actual_amount' => null,
        'currency' => 'USD',
    ])->assertRedirect();

    Notification::assertNotSentTo($owner, TripChangedNotification::class);
    Notification::assertSentTo($editor, TripChangedNotification::class);
});

test('shared inertia payload exposes unread count and recent items', function () {
    $owner = User::factory()->create(['name' => 'Owen Owner']);
    $editor = User::factory()->create(['name' => 'Eddie Editor']);
    $trip = makeSharedTrip($owner, $editor);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $response = $this->actingAs($owner)->get(route('trips.show', $trip));

    $shared = $response->viewData('page')['props']['notifications'];

    expect($shared['unread_count'])->toBe(1)
        ->and($shared['recent'])->toHaveCount(1)
        ->and($shared['recent'][0]['changed_area'])->toBe('tasks')
        ->and($shared['recent'][0]['actor_first_name'])->toBe('Eddie');
});

test('marking a notification as read clears it from unread count', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = makeSharedTrip($owner, $editor);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $owner->refresh();
    $notification = $owner->notifications()->first();

    expect($owner->unreadNotifications()->count())->toBe(1);

    $this->actingAs($owner)->post(route('notifications.read', $notification->id))->assertRedirect();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(0);
});

test('mark-all clears every unread notification for the user', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = makeSharedTrip($owner, $editor);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $this->actingAs($editor)->post(route('trips.costs.store', $trip), [
        'category' => 'food',
        'label' => 'Dinner',
        'currency' => 'USD',
    ])->assertRedirect();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(2);

    $this->actingAs($owner)->post(route('notifications.read-all'))->assertRedirect();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(0);
});

test('trip show payload exposes activity_version and last_event for polling', function () {
    $owner = User::factory()->create(['name' => 'Owen Owner']);
    $editor = User::factory()->create(['name' => 'Eddie Editor']);
    $trip = makeSharedTrip($owner, $editor);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $response = $this->actingAs($owner)->get(route('trips.show', $trip));

    $tripPayload = $response->viewData('page')['props']['trip'];

    expect($tripPayload['activity_version'])->toBeGreaterThan(0)
        ->and($tripPayload['last_event'])->not->toBeNull()
        ->and($tripPayload['last_event']['changed_area'])->toBe('tasks')
        ->and($tripPayload['last_event']['actor_first_name'])->toBe('Eddie');
});

test('a stranger cannot read another users notifications', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = makeSharedTrip($owner, $editor);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $notification = $owner->fresh()->notifications()->first();

    $this->actingAs($stranger)->post(route('notifications.read', $notification->id))->assertNotFound();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(1);
});
