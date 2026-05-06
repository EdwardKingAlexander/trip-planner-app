# Live Trip Collaboration Plan

## Status

- Module id: `live-trip-collaboration`
- Status: `planned`
- Current phase: `01-collaboration-event-foundation`
- State file: `ai/state/live-trip-collaboration.json`
- Last updated: `2026-05-06`

## Purpose

Make shared trip work feel live. When one attached user changes itinerary items, reservations, budget costs, packing, tasks, documents, reminders, imports, or collaborators, other eligible users should see a Facebook-style notification and the visible trip screen should update without a manual refresh.

## Existing Grounding

- Shared trip authorization already exists through `TripPolicy`, `Trip::visibleTo()`, and `Trip::canBeEditedBy()`.
- `trip_activity_events` already exists and can become the durable activity/audit source.
- `Trips/Show.vue` already receives the full trip detail prop from `TripController::show()`.
- Mutation controllers currently redirect back with flash messages but do not publish collaborator-facing changes.
- The app has no `routes/channels.php`, broadcast config, Laravel Echo client setup, notification table, or Reverb/Pusher dependency yet.

## Product Target

1. The editing user keeps the current form success flow.
2. Other trip participants receive a small in-app notification with actor, changed area, and summary.
3. If the affected trip is open, the page refreshes the needed Inertia props automatically.
4. The notification can be dismissed or used to jump to the affected section.
5. Notifications respect trip visibility and editor/viewer roles.

## Phase Index

1. [Phase 01 - Collaboration Event Foundation](01-collaboration-event-foundation.md)
2. [Phase 02 - Notification Center And Read State](02-notification-center-and-read-state.md)
3. [Phase 03 - Realtime Transport And Channel Authorization](03-realtime-transport-and-channel-authorization.md)
4. [Phase 04 - Live Trip Screen Refresh](04-live-trip-screen-refresh.md)
5. [Phase 05 - Coverage, Fallbacks, And Release Verification](05-coverage-fallbacks-and-release-verification.md)

## Dependency Decision

True push-based realtime updates require broadcast client/server dependencies that are not currently installed. The preferred implementation is Laravel Reverb plus Laravel Echo because it is the Laravel-native path for private-channel collaboration.

If dependency approval is not available during implementation, the fallback is an Inertia `usePoll()` and partial reload implementation. That fallback removes manual refreshes and can still show in-app notifications, but it is near-realtime instead of true push.

## Event Scope

Publish collaborator-visible events from:

- `TripController::update()`
- `TripItineraryController::store()`
- `TripReservationController::store()`
- `TripPlanningController::cost()`, `packing()`, `task()`, `document()`, and `reminder()`
- `TripImportController` review/commit flow
- `TripCollaboratorController::store()` and `destroy()`
- Future update/delete endpoints added for trip subresources

## State Update Protocol

1. Update `ai/state/live-trip-collaboration.json` before implementing each phase.
2. Keep phase files aligned with code status and verification results.
3. Do not mark a phase `verified` until focused backend tests and frontend type/lint checks pass.
4. If realtime dependencies are deferred, record the fallback mode in state before implementation.
