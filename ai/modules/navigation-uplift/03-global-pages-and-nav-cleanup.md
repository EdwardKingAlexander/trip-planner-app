# Phase 03 - Global Pages And Nav Cleanup

## Goal

Make every nav item resolve to a real, distinct page. Build the two missing global pages (Calendar, Reminders), wire the existing Notifications endpoint into the nav, and remove the items that don't belong in the global sidebar.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 02 (typed routes + active state working)
- Blocker: none

## Planned Changes

### Calendar — new global cross-trip view

#### Backend

- `app/Http/Controllers/CalendarController.php` (new) — `__invoke(Request $request)`.
- Loads the authenticated user's trips eager-loading `itineraryItems`, `reservations`, and `reminders` for the displayed month window.
- Returns `Inertia::render('calendar/Index', ['events' => ..., 'month' => ..., 'timezone' => ...])`.
- Event payload normalizes itinerary items, reservations, and reminders into a single shape: `{ id, kind, title, trip: {id, name}, startsAt, endsAt, allDay }`.
- Honors the user's `travel_preferences.timezone` for boundary calculation.

#### Route

- `Route::get('calendar', CalendarController::class)->middleware(['auth'])->name('calendar.index');` in `routes/web.php` (or the existing trip route file — match the convention already used for `trips.search`).
- Run the build so Wayfinder regenerates `resources/js/routes/calendar.ts`.

#### Frontend

- `resources/js/pages/calendar/Index.vue` (new).
  - Month grid with prev/next/today controls.
  - Each event chip is a typed `<Link>` to the relevant `trips.show` (with itinerary anchor) or detail page.
  - Empty state with a pulsing skeleton during deferred prop loads.
  - Responsive: month grid on desktop, agenda list (grouped by day) under `lg:`.

#### Test

- `tests/Feature/CalendarControllerTest.php` (Pest) — auth required, only own trips, month query param respected, timezone boundary correctness with two trips in different time zones.

### Reminders — new global cross-trip inbox

#### Backend

- `app/Http/Controllers/ReminderInboxController.php` (new) — `__invoke(Request $request)`.
- Returns the authenticated user's reminders across all trips, grouped by `Today`, `Upcoming`, `Past Due`, `Done`.
- Uses the existing reminder model + relationships introduced in earlier phases.

#### Route

- `Route::get('reminders', ReminderInboxController::class)->middleware(['auth'])->name('reminders.index');`
- Wayfinder regenerates on build.

#### Frontend

- `resources/js/pages/reminders/Index.vue` (new).
  - Sectioned list by status bucket.
  - Each row links to `trips.show` for that trip with a hash anchor to the reminder.
  - Mark-done action posts to the existing `trips.reminders.update` endpoint.

#### Test

- `tests/Feature/ReminderInboxControllerTest.php` — auth required, scoping to current user, sort order per bucket.

### Notifications — wire existing route into nav

- No backend or page work — `notifications.index` already exists.
- The Phase 02 nav already imports `from '@/routes/notifications'`. This phase confirms the entry renders, has an icon (`Bell` from `lucide-vue-next`), and shows a badge when there are unread notifications.
- Unread count comes from the existing Inertia shared props that the notification feature added in `live-trip-collaboration` module.

### Removals

- Reservations, Packing, Documents, Sharing items disappear from the global sidebar list.
- Each is reachable inside a trip via the existing tabs on the trip show page — no UX is lost, the path just changes from "click sidebar item that did nothing" to "open trip → click tab".
- Add a one-time toast or first-load banner is **not** in scope; the user reported the broken behavior and would prefer the items simply disappear.

## State Management

- New pages each manage their own component-local state (selected month, expanded reminder rows). No new global stores are introduced.
- The reminders mark-done action uses the existing optimistic-update pattern in the trip show pages — same composable, same rollback behavior. Do not invent a new pattern.
- The unread notification count flows through Inertia shared props as it already does; the nav consumes it as a computed and renders a badge when `> 0`.

## Acceptance Criteria

- Visiting `/calendar`, `/reminders`, and `/notifications` while authenticated each renders a page with content (or an empty state) — never a 404, never a redirect to `/trips`.
- Each new page passes its feature test.
- The Notifications nav item shows a badge when unread > 0 and no badge at zero.
- Sidebar contains exactly five items, in the order from Phase 01.
- Searching the codebase shows no remaining nav references to `/trips` for Calendar/Reservations/Packing/Documents/Sharing/Reminders.

## Out Of Scope

- Replacing the trip-internal Reservations/Packing/Documents/Sharing tabs (already shipped, untouched here).
- Drag-and-drop on the calendar.
- Real-time push of new notifications into the badge — handled by the live-trip-collaboration module already.
- Filters on the reminders inbox beyond the four status buckets.
