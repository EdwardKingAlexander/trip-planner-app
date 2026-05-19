# Phase 03 - Day Numbering And Travel Days

## Goal

Rewrite `Trip::syncDays()` so that:

1. Days are generated in the **destination timezone**, not in raw `YYYY-MM-DD` arithmetic.
2. Each `trip_day` carries a `kind` (`travel-out`, `arrival`, `vacation`, `departure`, `travel-home`) that describes what that calendar date means.
3. The "Day N" label is derived from the first `vacation` (or `arrival`) day, not from row position. A travel-only day shows as "Travel · Sep 26" instead of "Day 1".
4. The user can override the heuristic by marking any day as the explicit Day 1 — the rest renumber around it.

Same-timezone trips behave exactly as today (every day is `kind = vacation`, labels are "Day 1" through "Day N"). Cross-timezone trips finally express what the user means.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 02 (`destination_timezone` column exists and is populated for the test trip)
- Blocker: none

## Schema Changes

Migration: `database/migrations/2026_05_18_xxxxxx_add_kind_to_trip_days_table.php`

```php
Schema::table('trip_days', function (Blueprint $table) {
    $table->string('kind')->default('vacation')->after('title');
    $table->boolean('is_day_one_anchor')->default(false)->after('kind');
});
```

- `kind` enum values: `travel-out`, `arrival`, `vacation`, `departure`, `travel-home`. Stored as string to keep enum churn cheap.
- `is_day_one_anchor` — only one row per `trip_id` should be true. Enforced at write time, not via a unique index (so the user can flip it without first clearing the previous one).

Model: `app/Models/TripDay.php` gets `kind` and `is_day_one_anchor` in `$fillable` and `$casts` (`is_day_one_anchor => 'boolean'`).

## New Logic: `Trip::syncDays`

Replace the existing `syncDays` body. The new version:

1. Loads `destination_timezone` (via `effectiveDestinationTimezone()`).
2. Parses `starts_on` and `ends_on` as **dates in `destination_timezone`** — so `2026-09-26` means `2026-09-26T00:00:00+08:00` for a Manila trip.
3. Iterates from start to end one calendar day at a time in the destination timezone (handles DST without effort because we're stepping calendar dates, not seconds).
4. Determines the `kind` for each day:
   - First day: if there is a reservation of type `flight` whose `arrives_at` (in `arrival_timezone`) falls on the *next* calendar date in destination tz, this day is `travel-out`; otherwise `vacation` (or `arrival` if the first flight arrival is on this same destination-tz date).
   - Last day: mirror — if there is a return flight `departs_at` (in `departure_timezone`) that falls on this date and `arrives_at` falls on the next, this day is `departure`; if there's a separate `travel-home` overnight, that's a sixth row.
   - Middle days: `vacation`.
5. Preserves any `is_day_one_anchor = true` flag the user has set on an existing day (matched by `(trip_id, date)`).
6. Deletes only days whose `date` is outside the new range — never deletes a day the user has flagged as anchor unless the trip dates shrink past it.

Public helpers added alongside:

```php
public function dayOneAnchor(): ?TripDay
{
    return $this->days->firstWhere('is_day_one_anchor', true)
        ?? $this->days->firstWhere(fn (TripDay $d) => in_array($d->kind, ['vacation', 'arrival'], true));
}

public function vacationDayNumberFor(TripDay $day): ?int
{
    $anchor = $this->dayOneAnchor();
    if (! $anchor) {
        return null;
    }

    $anchorDate = Carbon::parse($anchor->date)->setTimezone($this->effectiveDestinationTimezone());
    $thisDate = Carbon::parse($day->date)->setTimezone($this->effectiveDestinationTimezone());
    $delta = $anchorDate->diffInDays($thisDate, false);

    if ($delta < 0) {
        return null;
    }

    return $delta + 1;
}

public function dayLabelFor(TripDay $day): string
{
    $n = $this->vacationDayNumberFor($day);
    return match (true) {
        $day->kind === 'travel-out' => 'Travel · departure',
        $day->kind === 'travel-home' => 'Travel · home',
        $day->kind === 'arrival' && $n !== null => "Arrival · Day {$n}",
        $day->kind === 'departure' && $n !== null => "Departure · Day {$n}",
        $n !== null => "Day {$n}",
        default => 'Travel',
    };
}
```

`TripController::tripDetail` then includes `kind`, `is_day_one_anchor`, and a precomputed `label` on each day in the payload, so the Vue side does no business logic.

## User-facing anchor override

A small action in the day card menu: "Mark as Day 1". Endpoint:

- `POST /trips/{trip}/days/{day}/anchor` — sets `is_day_one_anchor = true` on this day and `false` on all siblings. Authorized by `TripPolicy::update`. Records a `TripActivityEvent` so the live-collaboration channel rebroadcasts.

Inverse not needed — to remove an anchor the user marks a different day. The "default" anchor (the heuristic-chosen first vacation/arrival day) renders with a hollow icon; the user-chosen one renders solid.

## Heuristic detail: detecting travel-out / arrival from flights

Pseudo:

```php
foreach ($flights as $flight) {
    $arr = Carbon::parse($flight->arrives_at)->setTimezone($flight->arrival_timezone);
    $dep = Carbon::parse($flight->departs_at)->setTimezone($flight->departure_timezone);

    // Travel-out: a flight that arrives in destination tz on a later calendar day than it departed.
    if ($arr->toDateString() > $dep->toDateString()
        && $arrivalIsAtDestination($flight)
    ) {
        $travelOutDates[] = $dep->setTimezone($destinationTz)->toDateString();
        $arrivalDates[] = $arr->setTimezone($destinationTz)->toDateString();
    }
}
```

`$arrivalIsAtDestination($flight)` is a soft check — match on `arrival_airport` against a small airport→tz lookup (already exists via the airport-timezone hand-curated table from Phase 02). If we can't tell, the day stays `vacation` and the user can anchor manually. Heuristic correctness is not load-bearing — the anchor override is.

If no flights are stored, every day is `vacation` and the existing experience is preserved.

## Vue rendering changes

In `Trips/Show.vue`, the day card header switches from:

```vue
<CardTitle>{{ day.title }} · {{ formatDate(day.date) }}</CardTitle>
```

to:

```vue
<CardTitle>
    <span :class="day.kind.startsWith('travel') ? 'text-muted-foreground' : 'text-foreground'">
        {{ day.label }}
    </span>
    <span class="text-muted-foreground"> · {{ formatTripDate(day.date, trip.destination_timezone) }}</span>
    <button v-if="trip.can_edit && !day.is_day_one_anchor" @click="anchorDay(day.id)" class="...">
        Mark as Day 1
    </button>
</CardTitle>
```

(`formatTripDate` is delivered by Phase 04 — for Phase 03, leave a `// TODO Phase 04` comment and keep using the current `formatDate`. The display drift bug stays visible until Phase 04 lands; that's intentional so reviewers see what they're fixing.)

## State Management

- `trip_days.kind` and `trip_days.is_day_one_anchor` are server-owned. Frontend reads, never derives.
- The anchor mutation is a single POST; the server emits a `TripActivityEvent` so the existing `live-trip-collaboration` channel refreshes other viewers.
- No new global stores. The day card menu is local to each `<DayCard>`.

## Tests

`tests/Feature/Trips/TripSyncDaysTest.php`:

- `it generates days in destination timezone` — Manila trip, `starts_on = 2026-09-26`, `ends_on = 2026-09-28`. Asserts three days with dates `2026-09-26`, `2026-09-27`, `2026-09-28`.
- `it marks the first day travel-out when a flight arrives the next day in destination tz` — seed a LAX→MNL flight that departs `2026-09-26T22:00-07:00` and arrives `2026-09-28T06:00+08:00`. Assert first day `kind = travel-out`, second day `kind = arrival`.
- `it leaves every day vacation for a same-timezone trip` — LA→SF, no flights. All days `vacation`.
- `it preserves is_day_one_anchor when syncing days again on an unchanged date range`.
- `it clears is_day_one_anchor when the anchored date falls outside the new range`.
- `it survives a DST transition in the destination timezone` — London trip across the autumn DST change; assert correct day count.

`tests/Feature/Trips/TripDayAnchorTest.php`:

- `it sets is_day_one_anchor on the chosen day and clears siblings` — POST to `/trips/{trip}/days/{day}/anchor`, assert exactly one anchor.
- `it 403s a viewer who cannot edit the trip`.
- `it records a TripActivityEvent` — assert one event with `event_type = trip_day.anchor_set`.

`tests/Unit/TripDayLabelTest.php`:

- Table of `(kind, day_number) → label` expectations.

## Acceptance Criteria

- Migration applies cleanly and is reversible.
- `Trip::syncDays()` generates days in destination timezone for all four scenarios (same-tz / cross-tz / DST / single-day).
- The Manila example from the master plan: `starts_on = 2026-09-26`, `ends_on = 2026-10-05`, with a LAX→MNL flight whose arrival is `2026-09-27` in Manila time, produces days where `2026-09-26` is `travel-out` and `2026-09-27` is `arrival` with `label = "Arrival · Day 1"`.
- "Mark as Day 1" action works end to end: POST renumbers, day card updates, activity event recorded, other viewers refresh via the existing collaboration channel.
- `php artisan test --compact --filter='TripSyncDays|TripDayAnchor|TripDayLabel'` is green.
- `vendor/bin/pint --dirty --format agent` clean.
- No regressions in existing trip / itinerary / reservation tests.

## Out Of Scope

- Fixing the `new Date(yyyymmdd)` UTC-midnight bug in `formatDate` (Phase 04).
- A second-level anchor for "Day 1 of the return leg" (out — too niche).
- Per-day timezones (a road trip across timezones).
- Automatically reordering itinerary items when the anchor moves.
- Multi-leg trips with multiple `arrival` days.
- Visual transitions / animations when the anchor moves.
