# Phase 02 - Controller And Sync Logic

## Goal

Plug the pivot rebuild into the same DB transaction that writes a reservation, so every create/update produces correct day-link state with zero possibility of drift. The reservation row and its day spans always commit together.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 01
- Blocker: none

## Audit Inputs

- `app/Http/Controllers/TripReservationController.php:22-28` — `store()` already wraps the reservation create + `syncReservationDetails()` in `DB::transaction()`. The new pivot sync slots into this transaction.
- `app/Http/Controllers/TripReservationController.php:48-52` — `update()` mirror of the same pattern.
- `app/Http/Controllers/TripReservationController.php:160-216` — `syncReservationDetails()` is the existing junction-table sync for `flight_segments`, `lodging_stays`, and `flight_details`. The new call appends to this method.
- `app/Http/Controllers/TripReservationController.php:65-82` — `destroy()` already deletes the reservation; the pivot rows cascade via the FK from Phase 01, so no controller change is needed for delete.
- `app/Services/TripCollaborationEventService.php` (existing call sites at controller lines 30-37 and 54-61) — fires `reservation.*` events that already drive live-collaboration refresh. No new event type in this module.

## New Helper: `syncTripDaySpan()` (frozen)

A new private method on `TripReservationController`, called from `syncReservationDetails()` after all other detail syncs run:

```php
private function syncTripDaySpan(Reservation $reservation, Trip $trip): void
{
    $dates = $reservation->computeOverlappingDates($trip);

    if ($dates === []) {
        $reservation->tripDays()->sync([]);

        return;
    }

    $tripDayIds = $trip->days()
        ->whereIn('date', array_map(fn ($d) => $d->toDateString(), $dates))
        ->pluck('id')
        ->all();

    $reservation->tripDays()->sync($tripDayIds);
}
```

Notes:

- `sync()` (not `attach()` or `syncWithoutDetaching()`) — so when a reservation's `starts_at` is changed and the date span shrinks, the abandoned pivot rows are removed.
- Called after both the create path and the update path. `syncReservationDetails()` already runs in both; appending one line there covers both.
- `computeOverlappingDates()` is the single date-rules location from Phase 01. The controller never inlines the lodging/timezone logic.
- The reservation is reloaded with `tripDays` if any downstream code needs the relation, but the serializer in Phase 03 derives `day.reservations` from the `trip_days.reservations` side (eager-loaded once), so no reload here.

## Wiring Points

`TripReservationController::syncReservationDetails()` — append, just before the method returns at the existing line 215:

```php
$this->syncTripDaySpan($reservation, $reservation->trip);
```

`$reservation->trip` is already in scope on every code path that reaches this method (the reservation was just created or updated via `$trip->reservations()->...`); no explicit pass-through is needed beyond the helper signature.

## Edge Cases Frozen

| Scenario | Behavior |
| --- | --- |
| Reservation with `starts_at = null` | Pivot synced to empty array; the reservation is invisible on the itinerary tab. |
| Reservation type changed flight → lodging (or vice versa) during update | Existing `syncReservationDetails()` already deletes the wrong-type sidecar. The pivot is then resynced against the new lodging-exclusion rule. |
| Reservation's `ends_at` set before `starts_at` | Already rejected by `EndsAtAfterStarts` validation (`app/Rules/EndsAtAfterStarts.php`). The pivot sync never runs on a 422. |
| Trip's `destination_timezone` changes after the reservation was saved | This module does **not** resync pivots automatically on trip edit. The trip-timezones module already handles `Trip::syncDays`; if a future module wants reservation pivots resynced too, that's a one-line addition there. Logged as a known limitation in Phase 05. |
| Reservation extends past the trip's date envelope | `computeOverlappingDates()` clamps. Days outside the trip's `trip_days` table are silently skipped — the reservation still appears on every trip-day it overlaps. |
| Two reservations on the same day | Both produce their own pivot rows. The serializer sorts them by `starts_at` then `id`. |
| Concurrent updates to the same reservation by two collaborators | The pivot sync runs inside the same `DB::transaction()` that updates the reservation row. Last writer wins on both the reservation row and the pivot atomically. |

## Deliverables

- `app/Http/Controllers/TripReservationController.php` — add `syncTripDaySpan()`, call it from `syncReservationDetails()`.
- Feature test additions to `tests/Feature/TripReservationControllerTest.php` (or new file `tests/Feature/ReservationItineraryLinkTest.php`):
  - Creating a reservation populates the pivot.
  - Updating `starts_at` / `ends_at` rewrites the pivot.
  - Changing the reservation type from non-lodging to lodging applies the check-out-day exclusion.
  - Deleting a reservation cascades the pivot (assertion via direct DB query, since the relation is gone with the parent row).
  - A reservation with null `starts_at` produces no pivot rows.

## Acceptance Criteria

- All new feature tests pass: `php artisan test --compact --filter=ReservationItineraryLink`.
- Existing `TripReservationControllerTest` (and any sibling tests touching reservations) still pass without modification.
- `vendor/bin/pint --dirty --format agent` produces no output.
- A reservation create round-trips through the controller and the resulting `trip_day_reservation` rows match `computeOverlappingDates()`'s return exactly — verified by assertion.

## Out Of Scope For This Phase

- Serializer changes — Phase 03.
- Frontend rendering — Phase 04.
- Backfilling pre-existing reservations — Phase 05.
