# Reservation–Itinerary Link Master Plan

## Goal

When a user adds, edits, or deletes a reservation, the itinerary tab must reflect it immediately — every day the reservation covers shows the reservation in-line, alongside any free-form itinerary items the user has typed directly. Reservations become first-class itinerary entries; the itinerary view is the single, sorted day-by-day timeline of everything that's happening on this trip.

## Status

- Status: `verified`
- State file: `ai/state/reservation-itinerary-link.json`
- Last updated: 2026-05-21

## Problem

Today, reservations and itinerary items live as parallel, disconnected datasets:

- `reservations.itinerary_item_id` exists in the schema (`database/migrations/2026_05_04_000001_create_trip_management_tables.php:78`) and the Eloquent relation is defined (`app/Models/Reservation.php:51-54`, `app/Models/ItineraryItem.php:50-53`), but `TripReservationController::store/update` never populates it (`app/Http/Controllers/TripReservationController.php:22-28,46-52`). The column is dead weight.
- The itinerary tab in `resources/js/pages/Trips/Show.vue:1223-1286` iterates `day.items` — strictly `ItineraryItem` rows. Reservations are rendered in a completely separate Reservations tab (`Trips/Show.vue:1594+`).
- Net effect: a user adds a flight reservation for 2026-06-12 and the itinerary card for 2026-06-12 is silent about it. The only place that flight surfaces is the Reservations tab. The user has to mentally join the two.
- Multi-day reservations (a 5-night hotel) compound the problem: even if a single-day link existed, the lodging would only ever appear on the check-in day, not on each night.

## Strategy

Five phases. Each is independently reviewable; phases 03–05 can ship behind a Vue feature flag if needed, but the default plan is a single coordinated release.

1. **Drop the dead single-link column, add a day-span pivot.** `reservations.itinerary_item_id` cannot represent a reservation that spans multiple days, and it has never carried a value, so it is removed. A new `trip_day_reservation` pivot (`trip_day_id`, `reservation_id`) is the linkage — one row per (day, reservation) intersection. `Reservation::tripDays()` and `TripDay::reservations()` become the source of truth.
2. **Server-side sync on every reservation write.** `TripReservationController` recomputes the pivot inside the existing `DB::transaction()` block after the reservation row is saved. A new `Reservation::syncTripDaySpan()` helper computes the date list between `starts_at` and `ends_at` (both translated to the trip's destination timezone), intersects it with the trip's existing `trip_days` rows, and `sync()`s the pivot. Reservations with no `starts_at` produce zero pivot rows (silent on the itinerary, still visible in the Reservations tab).
3. **Serializer emits per-day reservations.** `TripController::show` (the page payload at `app/Http/Controllers/TripController.php:201-251`) gains a `day.reservations` array on each `days[*]` entry — same shape as `day.items`, sorted by `starts_at` then by `id`. The top-level `trip.reservations` array stays exactly as it is today (used by the Reservations tab).
4. **Itinerary tab renders reservations inline.** `Trips/Show.vue` iterates `day.reservations` immediately after `day.items` under each day card. Reservation entries are **read-only** in the itinerary view (no inline form, no inline destroy) — an "Open" button scrolls and focuses the matching card in the Reservations tab (reusing the `notification-deep-links` focus-target pattern). A small "Reservation" badge plus a per-type icon (flight ✈, lodging 🏨, etc.) distinguishes them from free-form items.
5. **Backfill, tests, gate.** A second migration walks every existing reservation that has a non-null `starts_at` and inserts its pivot rows so old trips look right immediately after deploy. Then the standard verification suite (Pest feature tests, type-check, lint, build, Pint).

## State Management

Server is the single source of truth. No optimistic UI, no client-side reservation→day joining.

- **Pivot truth**: `trip_day_reservation` table, keyed by `(trip_day_id, reservation_id)`. Rebuilt on every reservation create/update inside the same DB transaction that writes the reservation. Cascades on reservation delete (FK), cascades on trip_day delete (FK).
- **Per-day reservation list (serialized)**: computed server-side in `TripController::show` by reading the reservations side of the pivot per day. Sorted by `starts_at` then `id`. Emitted as `days[*].reservations`. Frontend never re-derives.
- **Reservation full list**: `trip.reservations` stays as-is. The Reservations tab is unaffected by this module.
- **Edit form state**: editing a reservation still happens in the Reservations tab's `editData` ref (`Trips/Show.vue:171-188`). The itinerary tab does not own any reservation form state — clicking "Open" on a reservation entry just deep-links to the existing edit surface.
- **Focus target**: the itinerary→reservations jump uses `focusTarget` query-string convention already established by `notification-deep-links` (`app/Services/NotificationDeepLinkResolver.php`).
- **Activity events**: no new event type. Existing `reservation.created` / `reservation.updated` / `reservation.deleted` events already cover the change. Live-trip collaboration pickup is automatic via the existing `activity_version` polling.

## Phases

1. [Data Model And Pivot](01-data-model-and-pivot.md)
2. [Controller And Sync Logic](02-controller-and-sync.md)
3. [Trip Serializer Per-Day Reservations](03-trip-serializer-merge.md)
4. [Itinerary Tab Rendering](04-frontend-itinerary-rendering.md)
5. [Backfill, Verification, Release](05-backfill-and-verification.md)

## Implementation Order

Phase 01 freezes the schema (drop column + pivot table + relations). Phase 02 writes the controller-side sync inside the existing reservation transaction so writes immediately produce correct pivot state. Phase 03 plumbs the data through the page payload. Phase 04 wires the UI. Phase 05 backfills existing rows, runs the verification gate, and ships.

## Acceptance Criteria

- A signed-in user with `trip.update` adds a flight reservation with `starts_at` and `ends_at` on the same day → the itinerary card for that day shows the reservation as a read-only entry with the flight title, time, type icon, and "Reservation" badge, plus an "Open" action that jumps to the Reservations tab with that reservation card focused.
- Adding a multi-day lodging reservation (e.g., check-in 2026-06-12, check-out 2026-06-15) → the reservation appears on the itinerary cards for 2026-06-12, 2026-06-13, and 2026-06-14 (check-out day excluded by default; see decision log). All three entries link back to the same reservation card.
- Editing a reservation's `starts_at` or `ends_at` recomputes the pivot inside the same transaction. The old day rows are removed, the new day rows are inserted, and the next page render shows the entry on the new days only.
- Deleting a reservation cascades pivot rows away via FK and the entry vanishes from every day card it appeared on.
- A reservation with `starts_at = null` produces zero pivot rows. It is invisible on the itinerary tab but still listed in the Reservations tab. This is intentional — the user hasn't told us when it happens.
- Existing reservations (created before this module shipped) appear on the correct day cards after the backfill migration runs, with no manual user action.
- Free-form `ItineraryItem` entries are not affected. The itinerary tab continues to render `day.items` and `day.tasks` exactly as before; `day.reservations` is appended after items, before tasks (or interleaved by sort key — decision locked in Phase 03).
- `reservations.itinerary_item_id` column is removed. Both Eloquent relations (`Reservation::itineraryItem`, `ItineraryItem::reservation`) are removed. Anywhere these are read (search, export, import) is updated.
- All standard checks pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`, `php artisan wayfinder:generate --with-form --no-interaction`.

## Decisions Locked In (planning pass — 2026-05-21)

- "Reservations ARE itinerary entries." No auto-created mirror `ItineraryItem` rows. The itinerary tab queries reservations directly via the pivot and renders them as first-class entries.
- "Every day in the range" multi-day rendering. The pivot has one row per day a reservation overlaps. Check-out day is **excluded** for `lodging` reservations (you sleep on nights 12–14, not on day 15), and included for everything else. The cutoff rule is encoded in `Reservation::computeOverlappingDates()`.
- "Backfill via migration." A second migration (`<TS>_backfill_reservation_trip_day_links.php`) runs after the main schema change and populates pivot rows for every reservation already in the table.
- "Read-only mirror on the itinerary tab, edited via the reservation." The itinerary tab never edits reservations in place. The reservation form continues to live in the Reservations tab as the single edit surface.
- The dead `reservations.itinerary_item_id` column is dropped (not kept as a legacy nullable). It has never been written to in any reachable code path; keeping it would invite future confusion.
- Timezone handling for day-span computation uses the trip's destination timezone (`trips.destination_timezone`, added in `2026_05_19_010530_add_timezones_to_trips_table.php`), falling back to the reservation's `starts_timezone`, then to UTC. Matches the convention `trip-timezones` module set for day labeling.
- No new activity event type. Existing `reservation.*` events cover all UI refresh needs through the live-collaboration polling already in place.

## Out Of Scope

- Sorting reservations and free-form items into a single interleaved timeline by `starts_at` (deferred to a follow-up; this module keeps items-then-reservations grouping for clarity and easier rollback).
- Drag-and-drop reordering of reservation entries on the itinerary tab.
- Allowing reservations with `starts_at = null` to be assigned to a specific day manually from the itinerary tab.
- Splitting a multi-day reservation across non-contiguous days (always a contiguous range).
- Surfacing per-day fragments of a long reservation as separate editable rows.
- Auto-creating an `ItineraryItem` from a reservation (rejected by the user in planning — reservations are the entry, not a mirror).
- Showing reservation cost annotations on the itinerary entry (the Costs tab remains the authority).
- Changing the Reservations tab — it stays the editing surface.
- Print, ICS export, JSON export changes (those layers already iterate `trip.reservations` directly and need no change for this module; revisit only if the audit in Phase 03 finds otherwise).
