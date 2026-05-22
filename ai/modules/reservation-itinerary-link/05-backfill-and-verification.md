# Phase 05 - Backfill, Verification, Release

## Goal

Populate `trip_day_reservation` rows for every reservation that already existed before this module shipped, run the full verification gate, and ship.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 04
- Blocker: none

## Audit Inputs

- Phase 01's `Reservation::computeOverlappingDates()` — the single source of truth for the date-span rules. The backfill calls it directly so no logic is duplicated.
- `database/migrations/2026_05_19_010530_add_timezones_to_trips_table.php` — confirms `trips.destination_timezone` is in place before any backfill runs. The backfill assumes destination_timezone is populated; trips with a null destination tz fall back to the reservation's `starts_timezone` (then UTC) per the helper rules.
- `app/Models/Trip.php::syncDays()` — already produces `trip_days` rows. The backfill assumes `trip_days` are present for every trip; trips with missing day rows (an unlikely but possible orphan state) skip silently. Logged in handoff notes.

## Backfill Migration (frozen)

New migration file: `database/migrations/<TIMESTAMP+1>_backfill_reservation_trip_day_links.php`. Timestamp must be **strictly later** than the pivot-table migration from Phase 01.

```php
public function up(): void
{
    // Reservations exist; pivot is empty (just created in the prior migration).
    // For every reservation with a starts_at, compute and insert pivot rows.

    \App\Models\Reservation::query()
        ->whereNotNull('starts_at')
        ->with('trip')
        ->chunkById(200, function ($reservations) {
            foreach ($reservations as $reservation) {
                $trip = $reservation->trip;
                if ($trip === null) {
                    continue;
                }

                $dates = $reservation->computeOverlappingDates($trip);
                if ($dates === []) {
                    continue;
                }

                $tripDayIds = $trip->days()
                    ->whereIn('date', array_map(fn ($d) => $d->toDateString(), $dates))
                    ->pluck('id')
                    ->all();

                if ($tripDayIds === []) {
                    continue;
                }

                $reservation->tripDays()->syncWithoutDetaching($tripDayIds);
            }
        });
}

public function down(): void
{
    // Reversal would require knowing which rows were "backfill" vs "live" — we don't track that.
    // Down is a no-op; rolling back this migration leaves the pivot in place.
    // The pivot itself is dropped by rolling back the Phase 01 migration.
}
```

Notes:

- `syncWithoutDetaching()` (not `sync()`) — the migration runs once and inserts; if it's run again accidentally it stays idempotent without removing rows. Belt-and-braces given that a fresh deploy will have an empty pivot anyway.
- `chunkById(200)` keeps memory bounded for any trip with a lot of reservations.
- The migration uses the same `computeOverlappingDates()` helper that the runtime sync uses — there is exactly one date-rules location, and backfill can't drift from runtime.
- Trips with no destination timezone fall through the helper's normal fallback chain. Logged.
- Trips missing `trip_days` rows skip silently — the pivot would have nothing to point to. Surfaced in the handoff notes for a follow-up if it actually happens.

## Verification Gate (frozen)

Run, in this order, and record results in `ai/state/reservation-itinerary-link.json` under `verification`:

```
php artisan migrate --no-interaction
php artisan test --compact --filter=ReservationItineraryLink
php artisan test --compact tests/Feature/TripManagementTest.php tests/Feature/TripReservationControllerTest.php
php artisan test --compact
php artisan wayfinder:generate --with-form --no-interaction
npm run types:check
npm run lint:check
npm run build
vendor/bin/pint --dirty --format agent
```

All must pass cleanly. Any failure halts the release.

## Manual Workflow Check (frozen)

After CI passes, walk through manually on a real trip with at least two destinations:

1. Create a single-day flight reservation → confirm it appears under the correct day card and on the Reservations tab.
2. Create a 4-night hotel reservation crossing a month boundary → confirm it appears on 4 day cards (not 5), each labeled "Night 1 of 4" through "Night 4 of 4".
3. Edit the hotel's check-out date to extend it by 1 night → confirm a fifth day card now shows the entry within seconds (after the page refresh / live-trip-collaboration tick).
4. Delete a reservation → confirm it disappears from every day card it was on.
5. Create a reservation with no `starts_at` → confirm it's listed on the Reservations tab but not on any day card.
6. Click "Open" on a day-card reservation entry → confirm it scrolls/focuses the Reservations tab card.
7. Confirm an old trip (created before this module) now shows its existing reservations on the right day cards immediately after the backfill migration ran.

## Documentation Updates

- `ai/modules/STATE.md` — append a row for this module (status `verified` after the gate passes).
- `ai/modules/README.md` — already updated as part of the planning pass to link this module.
- `ai/state/reservation-itinerary-link.json` — every `phase_status[*].status` flipped to `verified`, `current_phase` flipped to `05-backfill-and-verification`, `verification` array populated with the actual command output snippets.

## Known Limitations Logged At Release

- Trip timezone changes (`Trip::destination_timezone` updated after reservations exist) do not automatically resync pivots. The user must touch each reservation (any edit triggers a resync) for the day spans to update. Acceptable for a personal-use app; documented in the handoff section of the state file for a future module to address if it becomes a problem.
- Trips with missing `trip_days` rows (orphan state) have reservations that skip the pivot silently. Documented in handoff.
- Interleaved sorting of items + reservations within a day is not implemented — they render as two grouped blocks. Documented in handoff.

## Acceptance Criteria

- Every command in the verification gate passes.
- Every step in the manual workflow check passes.
- A grep audit confirms zero remaining references to `reservations.itinerary_item_id`, `Reservation::itineraryItem`, or `ItineraryItem::reservation` in `app/`, `routes/`, `tests/`, and `resources/js/`.
- `ai/state/reservation-itinerary-link.json` is updated with all phase statuses set to `verified` and the verification command outputs recorded.

## Risks

- The backfill migration touches every existing reservation. On a tiny personal dataset this is a non-issue; on a much larger dataset it could be slow. `chunkById(200)` keeps it safe.
- If `computeOverlappingDates()` has a bug, the backfill silently produces wrong pivot rows. Mitigated by Phase 01's unit test suite that locks the helper's behavior before the backfill ever runs.

## Out Of Scope

- Anything not in the verification gate.
- Performance profiling of the new eager loads (would only run if a real user-visible delay shows up).
- Surfacing reservations in ICS / JSON export / Print — those consumers already use the top-level `trip.reservations` array.
