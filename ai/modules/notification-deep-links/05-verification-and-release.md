# Phase 05 - Verification And Release

## Goal

Prove every notification subject type opens the correct tab + row, confirm fallbacks behave, and gate the release behind the standard test/lint/build/format suite.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 01–04
- Blocker: none

## Verification Matrix

For each subject type in the contract:

1. Trigger an activity event that emits a notification (use the existing `TripCollaborationEventService` test factories).
2. Open the bell, click the notification.
3. Assert the URL is `/trips/{id}?focus=<panel>&from=notification#<anchor>`.
4. Assert the right panel is active, the row is in the viewport, and the pulse fires.
5. Mark-read state flipped exactly once (no double-write).

Subject types: `TripItineraryItem`, `TripReservation`, `TripCost`, `PackingItem`, `TripTask`, `TripDocument`, `TripReminder`, `TripCollaborator`, plus the null/trip-level case and the stale-subject case.

## Programmatic Verification

- `php artisan test --compact` — full Pest suite. Includes the resolver unit test, the `go` controller test, and a new browser test exercising the focus + pulse on at least one subject type.
- `npm run lint:check`
- `npm run build` — regenerates Wayfinder for `notifications.go`.
- `npm run types:check` (after build).
- `vendor/bin/pint --dirty --format agent`

All five must pass.

## Drift Test

`tests/Unit/Notifications/DeepLinkContractTest.php` (new):

- Reads the subject map from `ai/state/notification-deep-links.json`.
- Compares against the resolver's internal map.
- Asserts they're identical.

This catches future code changes that quietly break the contract without updating the recorded state — same pattern as `navigation-uplift`.

## Manual Verification Script

1. Log in as a test user with collaborators on at least one trip.
2. Have the collaborator add an itinerary item, a reservation, a cost, a packing item, a task, a document, a reminder, and add a collaborator.
3. As the original user, open the bell. Click each notification in turn.
4. Confirm correct tab + scroll + pulse for each.
5. Have the collaborator delete one of the changed items. Click that notification — confirm the missing-item alert appears and the panel still loads.
6. Hard-refresh on each deep link URL — confirm the same behavior happens on a cold load.

## Regression Watch List

- Notification serializer payload size — adding `deep_link` adds bytes per row. Confirm the bell dropdown still loads under 100ms with 20 notifications.
- Trip show first-paint — focus targeting must not delay first paint of the trip overview when no `focus` param is present.
- Theme compatibility — pulse uses `--ring`; confirm contrast on every theme.
- Accessibility — pulse must not be the only signal for screen reader users (focus call is the parallel signal).

## Handoff

- Update `ai/state/notification-deep-links.json` with `status: verified`, fill `verification[]` with exact commands and results.
- Write a `handoff.summary` covering: where the resolver lives, where the contract is recorded, how to add a ninth subject type without breaking the drift test (edit resolver + state JSON in the same PR).

## Acceptance Criteria

- All programmatic checks pass.
- Manual script completes for all eight subject types plus the two fallbacks with no console errors and no broken links.
- A future agent can add a new subject type by editing only `app/Services/NotificationDeepLinkResolver.php`, the resolver test, and the state JSON.

## Out Of Scope

- Performance benchmarking beyond the basic regression check above.
- Visual regression / screenshot diffing.
- Marketing copy or release notes.
