# Phase 02 - Backend Targeted Notification

## Goal

When a packing item is toggled, route a personal "{Actor} packed the {label} you added" / "{Actor} unpacked the {label} you added" notification to the user who originally added the item, while keeping the generic `packing.toggled` broadcast intact for everyone else (and explicitly excluding the adder from that broadcast so they don't receive duplicate alerts).

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 01 (migration and serializer landed)
- Blocker: none

## Audit Inputs

- `app/Services/TripCollaborationEventService.php:22-83` — current `record()` builds the event, then calls `notifyParticipants()` which sends `TripChangedNotification` to every participant except the actor. There is no exclusion mechanism today.
- `app/Notifications/TripChangedNotification.php:1-44` — wraps a `TripActivityEvent`, serializes `event_type`, `summary`, `subject_type`, `subject_id`, `actor_*`, `trip_*`. Database channel only.
- `app/Http/Controllers/TripPlanningController.php:106-126` — current `togglePacked` calls `record()` once, with `eventType: 'packing.toggled'` and a generic `summary`.
- `app/Services/NotificationDeepLinkResolver.php` — resolves `subject_type` + `subject_id` to a deep link. Already handles `App\Models\PackingItem`.
- `app/Http/Controllers/NotificationController.php:69-87` — serializer reads `event_type` and `summary` straight from the notification `data`. New event types render automatically.

## Strategy

Two independent extensions, joined by the toggle controller:

1. Teach `TripCollaborationEventService::record()` to **exclude additional users** from the generic broadcast, via an opt-in `excludeUserIds` parameter (default empty). This keeps the existing call sites unchanged.
2. Introduce a thin `notifyAdder()` helper on the service (or a free-standing controller helper if the service feels overloaded — TBD in Phase 01 decisions) that sends a single targeted `TripChangedNotification` to the adder with a different `event_type` (`packing.packed_for_you` or `packing.unpacked_for_you`) and a personalized `summary` string.

The togglePacked controller orchestrates: it computes the adder, decides whether the adder is also the actor (short-circuit — no targeted notification, generic broadcast unchanged), and otherwise calls `record(... excludeUserIds: [adderId])` followed by `notifyAdder(... )`.

## Planned Changes

### `app/Services/TripCollaborationEventService.php`

#### `record()` signature

```php
public function record(
    Trip $trip,
    string $eventType,
    string $changedArea,
    string $summary,
    ?Model $subject = null,
    array $metadata = [],
    ?User $actor = null,
    array $excludeUserIds = [],   // NEW — optional list of user ids to exclude from the broadcast
): TripActivityEvent
```

The activity event row itself is unchanged; only the recipient set narrows.

#### `notifyParticipants()` change

Pass the `excludeUserIds` through to `participantsExceptActor()`, then filter again before sending.

```php
private function notifyParticipants(Trip $trip, TripActivityEvent $event, ?User $actor, array $excludeUserIds): void
{
    $recipients = $this->participantsExceptActor($trip, $actor)
        ->reject(fn (User $user) => in_array($user->id, $excludeUserIds, true))
        ->values();

    if ($recipients->isEmpty()) {
        return;
    }

    Notification::send($recipients, new TripChangedNotification($event));
}
```

#### `notifyAdder()` (new method)

Sends a focused notification to a single user. Uses the same `TripChangedNotification` class so the inbox UI and `NotificationDeepLinkResolver` keep working — only the underlying `TripActivityEvent`'s `event_type` and `summary` change.

```php
public function notifyAdder(
    Trip $trip,
    User $adder,
    string $eventType,
    string $changedArea,
    string $summary,
    ?Model $subject = null,
    array $metadata = [],
    ?User $actor = null,
): TripActivityEvent {
    $actor ??= Auth::user();

    $event = TripActivityEvent::create([
        'trip_id' => $trip->id,
        'user_id' => $actor?->id,
        'event_type' => $eventType,
        'changed_area' => $changedArea,
        'subject_type' => $subject?->getMorphClass(),
        'subject_id' => $subject?->getKey(),
        'summary' => $summary,
        'metadata' => array_merge([
            'actor_name' => $actor?->name,
            'actor_first_name' => $actor?->first_name,
            'trip_name' => $trip->name,
            'targeted_recipient_id' => $adder->id,
        ], $metadata),
    ]);

    Notification::send($adder, new TripChangedNotification($event));

    return $event;
}
```

The `targeted_recipient_id` metadata key lets the inbox / activity feed render the personalized variant differently if needed in the future.

### `app/Http/Controllers/TripPlanningController.php`

`togglePacked()` is rewritten to handle attribution:

```php
public function togglePacked(
    Request $request,
    Trip $trip,
    PackingItem $packingItem,
    TripCollaborationEventService $events,
): RedirectResponse {
    $this->authorize('update', $trip);
    abort_unless($packingItem->trip_id === $trip->id, 404);

    $validated = $request->validate(['is_packed' => ['required', 'boolean']]);
    $packingItem->update(['is_packed' => $validated['is_packed']]);

    $actor = $request->user();
    $adder = $packingItem->createdBy;     // may be null for legacy rows

    $isPacked = $validated['is_packed'];
    $genericSummary = ($isPacked ? 'marked packed: ' : 'marked unpacked: ').$packingItem->label;

    $excludeAdderFromBroadcast = $adder !== null && $adder->id !== $actor->id;

    $events->record(
        trip: $trip,
        eventType: 'packing.toggled',
        changedArea: 'packing',
        summary: $genericSummary,
        subject: $packingItem,
        excludeUserIds: $excludeAdderFromBroadcast ? [$adder->id] : [],
    );

    if ($excludeAdderFromBroadcast) {
        $personalSummary = sprintf(
            '%s %s the %s you added',
            $actor->first_name ?: $actor->name,
            $isPacked ? 'packed' : 'unpacked',
            $packingItem->label,
        );

        $events->notifyAdder(
            trip: $trip,
            adder: $adder,
            eventType: $isPacked ? 'packing.packed_for_you' : 'packing.unpacked_for_you',
            changedArea: 'packing',
            summary: $personalSummary,
            subject: $packingItem,
            actor: $actor,
        );
    }

    return back()->with('success', $isPacked ? 'Marked packed.' : 'Marked unpacked.');
}
```

Decision recorded: the new event types are **not** rolled into `packing.toggled` so the inbox / activity feed can style or filter them separately.

### `app/Services/NotificationDeepLinkResolver.php`

The resolver maps by `subject_type`. Since the targeted notifications still carry `subject_type = App\Models\PackingItem`, **no change is required** — they deep-link to the same packing row anchor. Confirm with a unit test in Phase 02 that the resolver returns the expected URL for the new `event_type`.

### Activity feed surface

If the trip activity feed renders `event_type` directly anywhere, add `packing.packed_for_you` and `packing.unpacked_for_you` to its allowlist or label map. (Audit during Phase 02 — the existing feed reads from `summary` so likely no work, but verify.)

## Tests

`tests/Feature/PackingAttributionNotificationTest.php` (new, Pest):

- `it('notifies the adder personally when someone else packs their item')` — adder Alex, actor Sam, third participant Pat. After toggle: Alex has 1 unread with `event_type = packing.packed_for_you` and personalized summary. Pat has 1 unread with `event_type = packing.toggled` and generic summary. Sam has 0 (actor).
- `it('notifies the adder personally on unpack too')` — same setup, toggle off, expect `packing.unpacked_for_you` and "Sam unpacked the Sunscreen you added".
- `it('does not notify the adder when the adder is the actor')` — Alex adds, Alex packs. Alex has 0 notifications. Pat has 1 generic. No targeted notification row created in `notifications`.
- `it('falls back to broadcast-only when the adder is null')` — legacy row with `created_by_user_id = null`. Sam packs. Pat (other participant) gets 1 generic. No targeted send attempted.
- `it('excludes the adder from the generic broadcast when sending the targeted one')` — assert Alex receives exactly one notification (the targeted one), not two.
- `it('records both events in trip_activity_events')` — one `packing.toggled` and one `packing.packed_for_you` row exist after the toggle.
- `it('still authorizes the toggle through the trip update policy')` — viewer-role collaborator gets 403; no notifications fire.

Existing `PackingTogglePackedTest` (from `packing-checkbox` module) continues to run unchanged. The actor-only test should not need updating — the broadcast still reaches non-adder participants the same way.

## State Management

- DB:
  - `packing_items.assigned_to_user_id` — assignment truth (Phase 01).
  - `trip_activity_events` — accumulates one row per emitted event; both generic and targeted variants land here.
  - `notifications` — Laravel database channel; targeted recipient gets a single row; broadcast recipients get one each.
- No caches, no queues changed (database channel is synchronous).
- `notifyAdder()` reuses `TripChangedNotification` so the existing serializer (`NotificationController::serialize`) renders it without changes.

## Acceptance Criteria

- `TripCollaborationEventService::record()` accepts `excludeUserIds` and uses it to filter the broadcast.
- `TripCollaborationEventService::notifyAdder()` exists and sends a single, distinct notification with a custom `event_type` and `summary`.
- `togglePacked` orchestrates broadcast exclusion + targeted send; adder never receives both.
- Adder-is-actor short-circuit prevents sending the targeted notification when it would echo back to the actor.
- Legacy rows with `created_by_user_id = null` continue to fan out the generic broadcast and never crash.
- All assertions in `PackingAttributionNotificationTest` pass.
- Existing `PackingTogglePackedTest` and `TripCollaborationEventServiceTest` (if present) still pass.
- `php artisan test --compact` is green.
- `vendor/bin/pint --dirty --format agent` is clean.

## Out Of Scope

- Frontend rendering of the targeted notification (it surfaces automatically in the inbox via the existing serializer; no extra work needed beyond confirming the deep link).
- Grouping / collapsing multiple targeted notifications for the same adder.
- Mute / opt-out controls for the targeted notification.
- Email / push delivery channels.
- A dedicated `PackingItemPackedForYouNotification` class — reusing `TripChangedNotification` keeps the deep link resolver and inbox UI uniform; revisit only if styling diverges.
