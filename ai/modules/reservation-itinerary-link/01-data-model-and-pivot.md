# Phase 01 - Data Model And Pivot

## Goal

Freeze the schema change. Drop the unused `reservations.itinerary_item_id` column and its relations, add the `trip_day_reservation` pivot, and freeze the date-span computation rules so Phases 02–04 build against a stable contract.

## Status

- Status: `verified`
- Owner: codex
- Depends on: none
- Blocker: none

## Audit Inputs

- `database/migrations/2026_05_04_000001_create_trip_management_tables.php:75-100` — current `reservations` table; line 78 is the `itinerary_item_id` foreign key being removed.
- `database/migrations/2026_05_04_000001_create_trip_management_tables.php:41-51` — `trip_days` table. The pivot FK targets this with `cascadeOnDelete`.
- `app/Models/Reservation.php:51-54` — `itineraryItem()` BelongsTo relation, to be deleted.
- `app/Models/ItineraryItem.php:50-53` — `reservation()` HasOne relation, to be deleted.
- `app/Models/Trip.php` (existing `syncDays()` / `dayLabelFor()`) — already produces the day list. The pivot intersects against `trip_days` keyed by date.
- `database/migrations/2026_05_19_010530_add_timezones_to_trips_table.php` — added `trips.destination_timezone` / `trips.home_timezone`. Destination tz is the primary source for the day-span computation.
- `app/Concerns/TracksAuthor.php` (assumed location based on package convention) — pivot rows do **not** use this trait. Audit ownership rides on the parent reservation.

## Migration 1 — Pivot Table + Drop Column (frozen)

New migration file: `database/migrations/<TIMESTAMP>_create_trip_day_reservation_table.php`.

```php
public function up(): void
{
    Schema::create('trip_day_reservation', function (Blueprint $table) {
        $table->id();
        $table->foreignId('trip_day_id')->constrained()->cascadeOnDelete();
        $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
        $table->timestamps();

        $table->unique(['trip_day_id', 'reservation_id']);
        $table->index(['reservation_id']);
    });

    Schema::table('reservations', function (Blueprint $table) {
        $table->dropConstrainedForeignId('itinerary_item_id');
    });
}

public function down(): void
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->foreignId('itinerary_item_id')->nullable()->after('trip_id')->constrained()->nullOnDelete();
    });

    Schema::dropIfExists('trip_day_reservation');
}
```

`unique(['trip_day_id', 'reservation_id'])` enforces the no-duplicates rule at the DB level so a buggy resync cannot stack rows.

The pivot table uses Laravel's pluralized-singular naming (`trip_day_reservation`) for `belongsToMany` autodiscovery; no custom table name needed on the relation.

The backfill migration is **not** in this phase — see Phase 05.

## Eloquent Relations (frozen)

`app/Models/Reservation.php` — remove `itineraryItem()`, add:

```php
public function tripDays(): BelongsToMany
{
    return $this->belongsToMany(TripDay::class)->orderBy('trip_days.date');
}
```

The Reservation `$fillable` loses `'itinerary_item_id'` (was at line 17).

`app/Models/ItineraryItem.php` — remove the `reservation()` HasOne relation entirely. The `Reservation` import line is also removed.

`app/Models/TripDay.php` — add:

```php
public function reservations(): BelongsToMany
{
    return $this->belongsToMany(Reservation::class)->orderBy('reservations.starts_at');
}
```

## Date-Span Computation Rules (frozen)

A new helper on the Reservation model:

```php
/**
 * Return the trip-local calendar dates this reservation overlaps.
 *
 * @return array<int, \Carbon\CarbonImmutable>
 */
public function computeOverlappingDates(Trip $trip): array;
```

Rules:

1. Resolve the working timezone in this order: `trips.destination_timezone` → `reservation.starts_timezone` → `'UTC'`.
2. If `starts_at` is null, return `[]`.
3. Convert `starts_at` to the working timezone and take its calendar date as the **first** date.
4. Convert `ends_at` (or `starts_at` if `ends_at` is null) to the working timezone and take its calendar date as the **last** date.
5. **Lodging exclusion**: if `reservation.type === 'lodging'` and `ends_at` is non-null and `ends_at`'s working-tz date is strictly greater than the first date, decrement the last date by one (you sleep on nights 12–14, not on day 15).
6. Clamp the resulting range against the trip's `trip_days` rows — never produce a date that has no `trip_day` record. (No silent creation of days; if the reservation extends outside the trip envelope, those days are skipped.)
7. Return the inclusive list as `CarbonImmutable` instances.

This helper is the **only** place the lodging rule and the timezone fallback live. Phase 02 calls it; the backfill migration in Phase 05 calls it.

## Authorization

Pivot writes inherit the parent `trip.update` authorization check that `TripReservationController::store` and `::update` already perform (`app/Http/Controllers/TripReservationController.php:18,43`). No new policy method. No direct pivot endpoint is exposed.

## Deliverables

- One new migration file: `<TS>_create_trip_day_reservation_table.php`.
- `app/Models/Reservation.php` updated: `$fillable` minus `itinerary_item_id`, `itineraryItem()` removed, `tripDays()` added, `computeOverlappingDates()` added.
- `app/Models/ItineraryItem.php` updated: `reservation()` removed, `Reservation` import removed.
- `app/Models/TripDay.php` updated: `reservations()` added.
- One new unit test (`tests/Unit/ReservationDateSpanTest.php`) covering: same-day reservation, multi-day non-lodging, multi-day lodging (check-out exclusion), null `starts_at` returns `[]`, timezone fallback chain, clamp against trip envelope.

## Acceptance Criteria

- `php artisan migrate` runs cleanly on a fresh DB.
- `php artisan migrate:rollback` cleanly reverses the new migration (column is restored, pivot is dropped).
- `Reservation::computeOverlappingDates()` returns the expected list for each unit-test scenario.
- No code in `app/`, `database/`, `routes/`, or `tests/` still references `reservations.itinerary_item_id`, `Reservation::itineraryItem`, or `ItineraryItem::reservation` (grep returns zero hits).
- `vendor/bin/pint --dirty --format agent` produces no output.

## Risks

- Removing `itinerary_item_id` is irreversible in production data terms (column drop). Mitigated by the fact that the column has never been populated by application code — a grep + dataset audit before the migration runs confirms it is empty.
- Multi-day reservations spanning a year-long trip with many `trip_days` could in principle insert hundreds of pivot rows. Acceptable for a personal-use vacation app; bounded by the inherent trip length.

## Verification Gate For This Phase

- New unit test file passes.
- Grep audit for stale references is clean.
- Pint clean.
