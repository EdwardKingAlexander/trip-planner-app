# Phase 01 - Audit And Subject Resolver Contract

## Goal

Lock the URL shape, the subject-to-panel mapping, and the deletion/missing-subject fallback rules before any code changes. Output is a contract every later phase consumes.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: none
- Blocker: none

## Audit Inputs

- `app/Services/TripCollaborationEventService.php` — emits every activity event with subject morph data.
- `app/Notifications/TripChangedNotification.php` — what's stored in the notification row.
- `app/Http/Controllers/NotificationController.php:57-72` — current serializer (drops subject metadata).
- `resources/js/components/NotificationBell.vue:52-66` — current click handler (trip-only redirect).
- `resources/js/pages/notifications/Index.vue:75-91` — current inbox link (trip-only).
- `resources/js/pages/Trips/Show.vue` — tab/panel ids: `itinerary`, `reservations`, `budget`, `packing`, `tasks`, `documents`, `reminders`, `sharing`.

## URL Shape (frozen)

Use a query parameter for the panel and a URL fragment for the entity anchor. Both are simple, stable, and survive Inertia partial reloads.

```
/trips/{tripId}?focus={panelId}&from=notification#{anchor}
```

- `focus` — required when targeting a specific panel; one of the panel ids above. Drives the active tab on first render.
- `from=notification` — telemetry-only marker so the trip show page can decide whether to pulse-highlight the focused row (only when arriving from a notification).
- Fragment — the row anchor (e.g., `#packing-item-87`). Native browser scroll honors it; Phase 04 adds the pulse.

Trip-level fallback (no subject):

```
/trips/{tripId}?from=notification
```

Stale-subject fallback (subject was deleted before the click):

- Resolver detects the subject row no longer exists, returns the panel-level URL with `&missing=1` so the page can show a one-line toast: "That item is no longer available."

## Subject → Panel Map (frozen)

| `subject_type` | `focus` panel | Anchor template |
| --- | --- | --- |
| `App\Models\TripItineraryItem` | `itinerary` | `itinerary-item-{id}` |
| `App\Models\TripReservation` | `reservations` | `reservation-{id}` |
| `App\Models\TripCost` | `budget` | `cost-{id}` |
| `App\Models\PackingItem` | `packing` | `packing-item-{id}` |
| `App\Models\TripTask` | `tasks` | `task-{id}` |
| `App\Models\TripDocument` | `documents` | `document-{id}` |
| `App\Models\TripReminder` | `reminders` | `reminder-{id}` |
| `App\Models\TripCollaborator` | `sharing` | `collaborator-{id}` |
| (null) | none (trip overview) | none |
| (unknown morph) | none | none |

Resolver uses Laravel's morph map (the same map already configured for activity events) so future model renames don't break links.

## Open Questions To Resolve Before Phase 02

- Confirm `from=notification` is the right marker name (alternatives: `notif`, `n`).
- Confirm pulse-highlight duration and easing (default proposal: 1200ms, `ease-out`, two flashes).
- Confirm we want a panel-level fallback for stale subjects rather than dropping to the trip overview.

Record answers in `ai/state/notification-deep-links.json` under `decisions[]` before starting Phase 02.

## State Management

- The contract is the state file. Source of truth lives in `ai/state/notification-deep-links.json` and is asserted by a drift test in Phase 05 — same pattern as `navigation-uplift`.
- No runtime state introduced in this phase.

## Acceptance Criteria

- Subject map, URL shape, and fallback rules are written into the state JSON.
- Open questions are answered.
- A grep over `app/Services/TripCollaborationEventService.php` for `subject:` confirms every call site's subject type is on the map (or explicitly listed as null/trip-level).

## Out Of Scope

- Implementing the resolver (Phase 02).
- Modifying any frontend (Phases 03 / 04).
