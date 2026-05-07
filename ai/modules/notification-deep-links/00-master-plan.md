# Notification Deep Links Master Plan

## Goal

When a user opens a notification (from either `NotificationBell` or `pages/notifications/Index.vue`), take them to the actual changed entity — the new reservation, the edited packing item, the added itinerary stop — not just the trip's home page.

## Status

- Status: `planned`
- State file: `ai/state/notification-deep-links.json`
- Last updated: 2026-05-06

## Problem

Notifications are emitted by `TripCollaborationEventService` and stored via `TripChangedNotification`, which records `subject_type` and `subject_id` in the database (`app/Notifications/TripChangedNotification.php:39-40`). The data is there.

The frontend never sees it:

- `NotificationController@serialize` returns `trip_id`, `summary`, `changed_area`, etc., but **drops** `subject_type` / `subject_id` (`app/Http/Controllers/NotificationController.php:57-72`).
- `NotificationBell.openNotification` always calls `router.visit('/trips/{trip_id}')` (`resources/js/components/NotificationBell.vue:60-62`).
- `pages/notifications/Index.vue` `<Link>` always points to `tripShow(notification.trip_id)`.

So a user gets pinged "Alex updated reservation: Marriott — King Suite", clicks the notification, and lands on the trip overview with no indication of which row changed. They have to click the Reservations tab and scan.

## Strategy

Three additions, in order:

1. **Surface the subject** in the notification serializer so the frontend has enough information to route precisely.
2. **Resolve the subject to a URL** server-side via a single `NotificationDeepLinkResolver` so subject-type → URL mapping has one home, not five.
3. **Honor focus targets on the trip show page** so a deep link like `/trips/12?focus=packing&entity=PackingItem-87` auto-selects the Packing tab, scrolls the row into view, and pulses it briefly.

The existing `PATCH /notifications/{id}/read` endpoint stays. We add a `GET /notifications/{id}/go` endpoint that marks read AND redirects to the resolved deep link in one round trip — so the frontend doesn't have to chain two requests.

## Subject Types In Scope

Drawn from `TripCollaborationEventService::record(...)` calls and the `subject_type` morph map. Each maps to a tab + entity anchor on the trip show page.

| `subject_type` | Trip-show panel | Anchor pattern |
| --- | --- | --- |
| `App\Models\TripItineraryItem` | `itinerary` | `#itinerary-item-{id}` |
| `App\Models\TripReservation` | `reservations` | `#reservation-{id}` |
| `App\Models\TripCost` | `budget` | `#cost-{id}` |
| `App\Models\PackingItem` | `packing` | `#packing-item-{id}` |
| `App\Models\TripTask` | `tasks` | `#task-{id}` |
| `App\Models\TripDocument` | `documents` | `#document-{id}` |
| `App\Models\TripReminder` | `reminders` | `#reminder-{id}` |
| `App\Models\TripCollaborator` | `sharing` | `#collaborator-{id}` |
| (null / trip-level event) | overview / first panel | none — fall back to trip show |

Polymorphic class names should resolve through the existing morph map (already used by activity events) so renames don't break links.

## Phases

1. [Audit And Subject Resolver Contract](01-audit-and-subject-resolver-contract.md)
2. [Backend Subject Routing And Read-And-Go Endpoint](02-backend-subject-routing-and-read-and-go-endpoint.md)
3. [Frontend Click Behavior](03-frontend-click-behavior.md)
4. [Trip Show Focus Targeting](04-trip-show-focus-targeting.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Run phases in order. The resolver contract (Phase 01) freezes URL shapes so backend (Phase 02), frontend (Phase 03), and trip show (Phase 04) can be developed against the same agreement. Phase 05 is the gate.

## Acceptance Criteria

- Clicking a notification (bell dropdown or inbox page) lands the user on the trip show page with the relevant tab open and the changed row visible in the viewport, briefly highlighted.
- Notifications without a subject (trip-level events, deleted subjects) still resolve cleanly to the trip overview — never a 404, never an empty page.
- The resolver lives in one place (`app/Services/NotificationDeepLinkResolver.php`) so adding a new subject type touches one file.
- Marking-as-read still happens exactly once per click (no double-call between read and redirect).
- All standard checks pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`.

## Out Of Scope

- Email or push delivery channels (still database-only).
- Multi-tab targeting (e.g., scroll within a sub-section of a tab).
- Bulk "open all unread in new tabs."
- Notification snooze, mute, or category filtering.
- Cross-trip aggregator routing (Reminders inbox in `navigation-uplift` module handles its own destinations).
