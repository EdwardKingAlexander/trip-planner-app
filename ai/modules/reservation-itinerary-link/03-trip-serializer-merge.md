# Phase 03 - Trip Serializer Per-Day Reservations

## Goal

Push the per-day reservation list down to the Inertia page payload as a new `days[*].reservations` array — sorted, minimal, and shaped identically to a card-friendly subset of `trip.reservations`. The Vue layer in Phase 04 reads it without re-deriving anything.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 02
- Blocker: none

## Audit Inputs

- `app/Http/Controllers/TripController.php:73-82` — current eager loads on the trip query. New: `days.reservations` (the pivot side).
- `app/Http/Controllers/TripController.php:201-229` — current `days` serializer block. New: append a `reservations` key on each day.
- `app/Http/Controllers/TripController.php:230-251` — current top-level `reservations` serializer block. **Unchanged** in this phase; the Reservations tab still reads from here.
- `app/Services/TripExportService.php`, `app/Http/Controllers/CalendarController.php`, `app/Services/TripImportParser.php` — out-of-band consumers of reservation data. Audit-only in this phase: confirm none of them iterate `day.reservations` (they shouldn't yet), so no change. Logged in state.

## Eager Loading (frozen)

`TripController::show()` adds two new `with()` entries:

```php
$trip->load([
    // ... existing loads
    'days.reservations',
    'days.reservations.flightSegments',
    'days.reservations.lodgingStay',
    'days.reservations.flightDetails',
]);
```

The deeper loads (`flightSegments`, `lodgingStay`, `flightDetails`) mirror the existing top-level `reservations.*` chain — necessary because the day-card view also wants the small type-icon and one-line provider summary. If runtime profiling shows this duplicates the load (the same reservation eager-loaded once via `trip->reservations` and again via `days.reservations`), we collapse to a single load and remap by ID. Default plan: ship the duplicate load; revisit only if profiler flags it.

## `days[*].reservations` Shape (frozen)

For each `day` in the `days` serializer block, append:

```php
'reservations' => $day->reservations
    ->sortBy([
        ['starts_at', 'asc'],
        ['id', 'asc'],
    ])
    ->values()
    ->map(fn ($reservation) => [
        'id' => $reservation->id,
        'type' => $reservation->type,
        'title' => $reservation->title,
        'provider_name' => $reservation->provider_name,
        'status' => $reservation->status,
        'starts_at' => $reservation->starts_at?->toIso8601String(),
        'starts_timezone' => $reservation->starts_timezone,
        'ends_at' => $reservation->ends_at?->toIso8601String(),
        'ends_timezone' => $reservation->ends_timezone,
        'location_name' => $reservation->location_name,
        'spans_multiple_days' => $reservation->tripDays->count() > 1,
        'day_index' => /* 1-based: which night/day of this reservation is this card? */,
        'day_total' => $reservation->tripDays->count(),
    ]),
```

Notes:

- Deliberately **smaller** than the top-level `trip.reservations` shape — no `flight_segments`, `flight_details`, `lodging_stay`, `notes`, `contact_*`. The day card is a glanceable summary; the user clicks "Open" to see the full reservation card on the Reservations tab.
- `spans_multiple_days`, `day_index`, `day_total` give the UI everything it needs to render "Night 2 of 3" or "Day 1 of 4" badges without re-deriving on the client.
- `day_index` is computed by sorting `$reservation->tripDays` by `date` and finding the position of the current day. Stable across renders.
- The 1-based index excludes the lodging check-out day (because Phase 01 already excluded it from the pivot). So a 3-night hotel shows day_index 1, 2, 3 — not 1, 2, 3, 4.

## TypeScript Type (frozen)

In the existing trip-show types module (likely `resources/js/types/trip.ts` or inline in `Trips/Show.vue` — audit to confirm location):

```ts
export type TripDayReservationEntry = {
  id: number;
  type: string;
  title: string;
  provider_name: string | null;
  status: string;
  starts_at: string | null;
  starts_timezone: string;
  ends_at: string | null;
  ends_timezone: string;
  location_name: string | null;
  spans_multiple_days: boolean;
  day_index: number;
  day_total: number;
};
```

The existing `TripDay` page-payload type gains:

```ts
reservations: TripDayReservationEntry[];
```

## Out-Of-Band Consumer Audit (deliverable)

Before this phase ships, confirm that the following do **not** need the new shape (audit + decision; no code change in this phase unless audit finds otherwise):

- `app/Services/TripExportService.php` — JSON export iterates `trip.reservations` directly. Stays.
- `app/Http/Controllers/CalendarController.php` — already builds ICS from `trip.reservations`. Stays.
- `resources/js/pages/Trips/Print.vue` — print view; audit which array it reads.
- `app/Services/NotificationDeepLinkResolver.php` — already targets reservation rows by ID. Stays.

Findings are recorded in `ai/state/reservation-itinerary-link.json` under `audit_findings`.

## Deliverables

- `app/Http/Controllers/TripController.php` — eager loads + `days[*].reservations` block.
- TypeScript type addition for `TripDayReservationEntry`.
- New feature test in `tests/Feature/ReservationItineraryLinkTest.php` (or extend Phase 02's file): GET the trip show payload, assert each day's `reservations` array has the expected reservation IDs, in the expected order, with the expected `day_index` / `day_total` / `spans_multiple_days` values.

## Acceptance Criteria

- The trip show payload includes `days[*].reservations` populated correctly for: a single-day reservation, a 3-night lodging stay (visible on 3 days, not 4), a flight that lands on day 2, two reservations on the same day (sorted by `starts_at`), and an unscheduled reservation (`starts_at = null` → appears on zero days).
- The top-level `trip.reservations` array is byte-identical to its pre-module shape (the Reservations tab is unaffected).
- `npm run types:check` passes.
- Feature test passes.
- `vendor/bin/pint --dirty --format agent` produces no output.

## Risks

- N+1: eager loads on `days.reservations` plus the existing `trip.reservations` chain risk double-loading reservation rows. Acceptable cost for a personal vacation app. Mitigate only if profiler complains.
- Payload size: every reservation that spans N days appears N times in the new structure. For typical trips (under 20 reservations, under 20 days, average span of 1–2 days), the bloat is bounded at ~200 small JSON objects. Fine.

## Out Of Scope

- Eliminating the duplicated eager load.
- Adding reservations to ICS / JSON / Print outputs (those layers already use the top-level array).
- Changing the top-level `trip.reservations` shape.
