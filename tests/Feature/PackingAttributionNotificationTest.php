<?php

use App\Models\Trip;
use App\Models\TripActivityEvent;
use App\Models\User;
use App\Services\NotificationDeepLinkResolver;

function makePackingNotificationTrip(User $owner, ?User $editor = null, ?User $third = null): Trip
{
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);

    foreach ([$editor, $third] as $user) {
        if ($user === null) {
            continue;
        }

        $trip->collaborators()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => 'editor',
            'accepted_at' => now(),
        ]);
    }

    return $trip;
}

function clearPackingNotificationInboxes(User ...$users): void
{
    foreach ($users as $user) {
        $user->notifications()->delete();
    }
}

test('notifies the adder personally when someone else packs their item', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $editor = User::factory()->create(['name' => 'Sam Editor']);
    $third = User::factory()->create(['name' => 'Pat Participant']);
    $trip = makePackingNotificationTrip($owner, $editor, $third);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => '',
        'assigned_to_user_id' => $editor->id,
        'category' => 'toiletries',
        'label' => 'Sunscreen',
        'quantity' => 1,
        'notes' => null,
    ])->assertRedirect();

    $item = $trip->packingItems()->first();
    clearPackingNotificationInboxes($owner, $editor, $third);

    $this->actingAs($editor)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertRedirect();

    $ownerNotification = $owner->fresh()->notifications()->sole();
    $thirdNotification = $third->fresh()->notifications()->sole();

    expect($ownerNotification->data['event_type'])->toBe('packing.packed_for_you')
        ->and($ownerNotification->data['summary'])->toBe('Sam packed the Sunscreen you added')
        ->and($thirdNotification->data['event_type'])->toBe('packing.toggled')
        ->and($thirdNotification->data['summary'])->toBe('marked packed: Sunscreen')
        ->and($editor->fresh()->notifications()->count())->toBe(0)
        ->and($owner->fresh()->notifications()->count())->toBe(1);
});

test('notifies the adder personally when someone else unpacks their item', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $editor = User::factory()->create(['name' => 'Sam Editor']);
    $third = User::factory()->create(['name' => 'Pat Participant']);
    $trip = makePackingNotificationTrip($owner, $editor, $third);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => '',
        'assigned_to_user_id' => $editor->id,
        'category' => 'toiletries',
        'label' => 'Sunscreen',
        'quantity' => 1,
        'is_packed' => true,
        'notes' => null,
    ])->assertRedirect();

    $item = $trip->packingItems()->first();
    clearPackingNotificationInboxes($owner, $editor, $third);

    $this->actingAs($editor)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => false])
        ->assertRedirect();

    $ownerNotification = $owner->fresh()->notifications()->sole();
    $thirdNotification = $third->fresh()->notifications()->sole();

    expect($ownerNotification->data['event_type'])->toBe('packing.unpacked_for_you')
        ->and($ownerNotification->data['summary'])->toBe('Sam unpacked the Sunscreen you added')
        ->and($thirdNotification->data['event_type'])->toBe('packing.toggled')
        ->and($thirdNotification->data['summary'])->toBe('marked unpacked: Sunscreen');
});

test('does not notify the adder personally when the adder is the actor', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $third = User::factory()->create(['name' => 'Pat Participant']);
    $trip = makePackingNotificationTrip($owner, third: $third);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => '',
        'assigned_to_user_id' => $owner->id,
        'category' => 'toiletries',
        'label' => 'Sunscreen',
        'quantity' => 1,
        'notes' => null,
    ])->assertRedirect();

    $item = $trip->packingItems()->first();
    clearPackingNotificationInboxes($owner, $third);

    $this->actingAs($owner)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertRedirect();

    expect($owner->fresh()->notifications()->count())->toBe(0)
        ->and($third->fresh()->notifications()->sole()->data['event_type'])->toBe('packing.toggled')
        ->and($trip->activityEvents()->whereIn('event_type', ['packing.packed_for_you', 'packing.unpacked_for_you'])->count())->toBe(0);
});

test('falls back to broadcast only when the adder is missing', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $editor = User::factory()->create(['name' => 'Sam Editor']);
    $third = User::factory()->create(['name' => 'Pat Participant']);
    $trip = makePackingNotificationTrip($owner, $editor, $third);
    $item = $trip->packingItems()->create([
        'category' => 'toiletries',
        'label' => 'Legacy Sunscreen',
        'quantity' => 1,
        'created_by_user_id' => null,
        'updated_by_user_id' => null,
    ]);

    $this->actingAs($editor)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertRedirect();

    expect($owner->fresh()->notifications()->sole()->data['event_type'])->toBe('packing.toggled')
        ->and($third->fresh()->notifications()->sole()->data['event_type'])->toBe('packing.toggled')
        ->and($trip->activityEvents()->where('event_type', 'packing.packed_for_you')->count())->toBe(0);
});

test('records both generic and targeted events and keeps deep links on the packing row', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $editor = User::factory()->create(['name' => 'Sam Editor']);
    $trip = makePackingNotificationTrip($owner, $editor);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => '',
        'assigned_to_user_id' => $editor->id,
        'category' => 'toiletries',
        'label' => 'Sunscreen',
        'quantity' => 1,
        'notes' => null,
    ])->assertRedirect();

    $item = $trip->packingItems()->first();
    clearPackingNotificationInboxes($owner, $editor);

    $this->actingAs($editor)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertRedirect();

    expect(TripActivityEvent::where('trip_id', $trip->id)->where('event_type', 'packing.toggled')->count())->toBe(1)
        ->and(TripActivityEvent::where('trip_id', $trip->id)->where('event_type', 'packing.packed_for_you')->count())->toBe(1);

    $notification = $owner->fresh()->notifications()->sole();
    $url = app(NotificationDeepLinkResolver::class)($notification);

    expect($url)->toContain('focus=packing')
        ->and($url)->toContain("#packing-item-{$item->id}");
});

test('viewers cannot toggle packed state or send notifications', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $viewer = User::factory()->create(['name' => 'Val Viewer']);
    $trip = makePackingNotificationTrip($owner);
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);
    $item = $trip->packingItems()->create([
        'category' => 'toiletries',
        'label' => 'Sunscreen',
        'quantity' => 1,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->patch(route('trips.packing-items.toggle-packed', [$trip, $item]), ['is_packed' => true])
        ->assertForbidden();

    expect($owner->fresh()->notifications()->count())->toBe(0)
        ->and($trip->activityEvents()->where('event_type', 'packing.toggled')->count())->toBe(0);
});
