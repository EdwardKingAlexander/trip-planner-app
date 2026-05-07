# Phase 05 - Verification And Release

## Goal

Prove every navigation item works on every viewport, every authenticated layout, and every entry path; then gate the release behind the standard test/lint/build/format suite and write a handoff that future agents can rely on.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phases 01–04
- Blocker: none

## Verification Matrix

For each viewport (`mobile`, `tablet`, `desktop`):

For each global nav item (Trips, Search, Calendar, Reminders, Notifications):

1. Click from a fresh page load on the dashboard.
2. Confirm the URL becomes the expected route name's path.
3. Confirm the active-state ring/underline highlights only that one item.
4. Hard-reload the destination — confirm the active state survives reload.
5. Browser console is free of warnings/errors during the click.

Spot-check additional flows:

- Click Trips, then click Search — Trips active state should release, Search should activate.
- Open `/trips/{trip}/edit` directly — Trips should be active (prefix match), Search should NOT be active (excluded prefix).
- On mobile: open the offcanvas sidebar, tap each item, confirm the sheet closes and the page navigates.
- On the header's Search icon (if kept) — clicking lands on `trips.search`.
- Notification badge: with at least one unread notification, badge is visible; mark all read, badge disappears without a reload.

## Programmatic Verification

Run from project root:

- `php artisan test --compact` — full Pest suite, including the new `CalendarControllerTest`, `ReminderInboxControllerTest`, and `useCurrentUrl` unit test.
- `npm run lint:check` — ESLint clean.
- `npm run types:check` — Vue + TS clean. Run after `npm run build` so Wayfinder regenerates `resources/js/routes/calendar.ts` and `resources/js/routes/reminders.ts`.
- `npm run build` — production Vite build.
- `vendor/bin/pint --dirty --format agent` — PHP formatting.
- `tests/Browser/AuthenticatedNavigationTest.php` (new, Pest browser): authenticate, visit `/`, click each nav item in turn, assert URL change and absence of JS errors.

All six must pass before this phase is marked `verified`.

## Drift Test

Add `tests/Unit/Navigation/NavigationContractTest.php` (PHPUnit/Pest) that:

- Reads the active-state rule table from `ai/state/navigation-uplift.json`.
- Decodes the same rules as exported from `resources/js/lib/navigation.ts` (export them as a JSON-compatible structure for the test, or read via a build artifact).
- Asserts the two are identical.

This catches future code changes that quietly break the IA contract without updating the recorded state.

## Manual Verification Script

1. Log in as a test user with at least two trips, mixed-timezone trips, a few reservations, and at least one reminder.
2. From `/trips`, click each global nav item in order; confirm a distinct destination renders for each.
3. Open `/trips/{id}` and confirm the in-trip Reservations / Packing / Documents / Sharing tabs still work (regression check after their sidebar removal).
4. Resize to mobile, repeat steps 2–3 using the offcanvas menu.
5. Hard refresh on each destination; confirm no flash, no 404, correct active item.

## Regression Watch List

- Sidebar sticky scroll behavior must not regress (`scroll-issue` module shipped this).
- Themed colors on active state must hold across all five themes if the `themes-plan` module has shipped by the time this lands (`themes-plan/03-five-premade-themes.md`).
- Notification unread badge must not double-count after a `live-trip-collaboration` realtime delivery.

## Handoff

- Update `ai/state/navigation-uplift.json` with `status: verified`, fill `verification[]` with exact commands and results, and write a one-paragraph `handoff.summary` covering: where the global item list lives, where the active-state rules live, how to add a sixth item without breaking the contract test.
- If a project-wide STATE.md tracks cross-module decisions, add an entry recording the IA decision (Reservations/Packing/Documents/Sharing live as in-trip tabs only).

## Acceptance Criteria

- All programmatic checks pass.
- Manual script completes with no console errors and no broken links.
- `ai/state/navigation-uplift.json` reflects the final state.
- A future agent can add a sixth global nav item by editing only `resources/js/lib/navigation.ts`, the route definition, and the state JSON — no other files should need to change.

## Out Of Scope

- Performance benchmarking of nav rendering.
- Visual-regression screenshots.
- Marketing copy for any of the new pages.
