# Phase 02 - Schema And Timezone Fields

## Goal

Add a `destination_timezone` (authoritative) and `home_timezone` (informational) to the `trips` table. Wire both into the trip create / edit form via a searchable picker, with sensible defaults so the user is never blocked by a required field they don't understand. Backfill existing trips conservatively (hand-curated table for the top destinations; otherwise leave `null` and surface a one-time inline prompt on the trip detail screen).

This phase is data + form only. The day-numbering and rendering changes are Phase 03 and Phase 04 — they depend on these columns existing.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01 (`audit_findings` reviewed, edit-affordance question answered)
- Blocker: none

## Schema Changes

Migration: `database/migrations/2026_05_18_xxxxxx_add_timezones_to_trips_table.php`

```php
Schema::table('trips', function (Blueprint $table) {
    $table->string('destination_timezone')->nullable()->after('destination');
    $table->string('home_timezone')->nullable()->after('destination_timezone');
});
```

- `destination_timezone` is an IANA name (e.g. `Asia/Manila`, `Europe/London`, `America/Los_Angeles`). Stored nullable so existing rows survive the migration. New trips created after this phase ships must set it (form-level required; server-level required after a two-week soak so any backfill prompts are resolved).
- `home_timezone` is also IANA. Falls back at read time to `UserTravelPreference.home_timezone` (already exists per Phase 03 of the original build), then `config('app.timezone')`. Never blocking.

Model casts and accessors in `app/Models/Trip.php`:

```php
protected $fillable = [
    // existing...
    'destination_timezone',
    'home_timezone',
];

public function effectiveDestinationTimezone(): string
{
    return $this->destination_timezone ?? 'UTC';
}

public function effectiveHomeTimezone(): string
{
    return $this->home_timezone
        ?? $this->user?->travelPreference?->home_timezone
        ?? config('app.timezone');
}
```

Both accessors are deliberately not magic attributes so the raw nullable value remains inspectable.

## Hand-Curated Backfill Table

Seeder-style data migration (a second migration file, not the schema one above) that infers `destination_timezone` from `destination` text using a hand-written table. Keep it short — the goal is to cover the obvious cases, not to be a geocoder.

```php
$lookup = [
    'manila' => 'Asia/Manila',
    'philippines' => 'Asia/Manila',
    'tokyo' => 'Asia/Tokyo',
    'japan' => 'Asia/Tokyo',
    'london' => 'Europe/London',
    'paris' => 'Europe/Paris',
    'rome' => 'Europe/Rome',
    'barcelona' => 'Europe/Madrid',
    'amsterdam' => 'Europe/Amsterdam',
    'berlin' => 'Europe/Berlin',
    'reykjavik' => 'Atlantic/Reykjavik',
    'iceland' => 'Atlantic/Reykjavik',
    'sydney' => 'Australia/Sydney',
    'auckland' => 'Pacific/Auckland',
    'bali' => 'Asia/Makassar',
    'bangkok' => 'Asia/Bangkok',
    'singapore' => 'Asia/Singapore',
    'hong kong' => 'Asia/Hong_Kong',
    'seoul' => 'Asia/Seoul',
    'dubai' => 'Asia/Dubai',
    'new york' => 'America/New_York',
    'los angeles' => 'America/Los_Angeles',
    'san francisco' => 'America/Los_Angeles',
    'chicago' => 'America/Chicago',
    'denver' => 'America/Denver',
    'honolulu' => 'Pacific/Honolulu',
    'hawaii' => 'Pacific/Honolulu',
    'cancun' => 'America/Cancun',
    'mexico city' => 'America/Mexico_City',
    'buenos aires' => 'America/Argentina/Buenos_Aires',
    'rio' => 'America/Sao_Paulo',
    'cape town' => 'Africa/Johannesburg',
];
```

Match logic: lowercase the `destination` string, check whether any key is a substring. First match wins. Unmatched trips stay `null` and Phase 02 ships an inline banner on `Trips/Show.vue` that says "We don't know what timezone {destination} is in — pick one" with the same picker the edit form uses.

## Form Wiring

### Trip create / edit form

The trip form lives in the `Trips/Show.vue` edit sheet (per `editable-trip-entries`) plus the trip create form. Both need:

- A new `<TimezonePicker>` component at `resources/js/components/TimezonePicker.vue`.
- Two fields: `destination_timezone` (required for new trips), `home_timezone` (optional, placeholder = the user's preference or "Your home time").

Validation in `app/Http/Controllers/TripController.php::validateTrip`:

```php
'destination_timezone' => ['required', 'string', new TimezoneRule()],
'home_timezone' => ['nullable', 'string', new TimezoneRule()],
```

`TimezoneRule` lives at `app/Rules/TimezoneRule.php` and validates against `\DateTimeZone::listIdentifiers()`. The `required` rule is conditional for the create endpoint and the edit endpoint (when the existing value was non-null); existing trips with `null` continue to accept submits that don't set the field, but the inline banner pushes the user to set it.

### TimezonePicker component

- Combobox (use the existing shadcn-vue `Combobox` already used elsewhere in the app — confirm during Phase 02).
- Options: `\DateTimeZone::listIdentifiers()` shipped to the page once via a shared prop (`timezones`), grouped by continent.
- "Recent" section pinned at the top: the user's own `home_timezone`, the trip's current value, the destination-text guess from the hand-curated lookup.
- Searchable by IANA name or by a tiny synonyms map (`"manila" → "Asia/Manila"`, `"hawaii" → "Pacific/Honolulu"`, `"uk" → "Europe/London"`). Synonyms live next to the lookup table.
- Display label format: `Asia/Manila (PHT, UTC+8)`. Computed on the client at render time so DST is correct.

### Inline backfill prompt

If `destination_timezone === null` on the loaded trip and the current user `canBeEditedBy`, render a non-dismissable banner above the day list:

> **Set the timezone for {destination}** — until you do, day numbering and calendar placement use UTC and may look wrong.

Tapping it opens the same edit sheet with the timezone picker pre-focused.

## Wayfinder

The existing trip update route is unchanged in URL / method. After this phase ships, run `php artisan wayfinder:generate` so the typed payload on the frontend gains `destination_timezone` and `home_timezone`.

## State Management

- New columns on `trips`. No new tables.
- No new global stores. The picker is a local `<select>` inside the existing form.
- Banner visibility is derived in `Trips/Show.vue` from `trip.destination_timezone === null` — no new state.

## Tests

`tests/Feature/Trips/TripTimezoneTest.php`:

- `it sets destination_timezone on trip create` — POST with `destination_timezone = 'Asia/Manila'`, assert stored.
- `it rejects an invalid timezone identifier` — POST with `'Mars/Olympus_Mons'`, assert 422.
- `it allows nullable home_timezone` — POST without it, assert null stored.
- `it leaves existing null destination_timezone alone on partial update` — PATCH another field, assert tz still null.
- `it accepts editing destination_timezone to a new value` — PATCH `destination_timezone = 'Europe/London'`, assert stored.
- `it backfills well-known destinations` — run the data migration, assert a seeded Manila trip got `Asia/Manila`.

`tests/Feature/Trips/TripTimezoneBackfillTest.php`:

- `it leaves unknown destinations as null` — seed `destination = 'Some Tiny Town'`, run migration, assert null.
- `it is case insensitive and substring matches` — seed `destination = 'MANILA, Philippines'`, assert `Asia/Manila`.

Vue / Vitest (only if `npm run test:unit` is wired; otherwise defer to manual): `TimezonePicker` renders the recent group and filters by synonym.

## Acceptance Criteria

- Migration applies cleanly and is reversible.
- Backfill data migration runs idempotently and only touches rows where `destination_timezone IS NULL`.
- Trip create form sends `destination_timezone`; validation rejects unknown identifiers.
- Trip edit form lets the user change `destination_timezone` and `home_timezone`. The change is reflected on reload.
- Inline backfill banner renders only for trips with `destination_timezone IS NULL` and `canBeEditedBy = true`.
- `Trip::effectiveDestinationTimezone()` and `Trip::effectiveHomeTimezone()` return sensible defaults for trips that haven't been backfilled.
- Wayfinder types regenerated; `npm run types:check` passes.
- `php artisan test --compact --filter=TripTimezone` is green.
- `vendor/bin/pint --dirty --format agent` clean.

## Out Of Scope

- Using `destination_timezone` anywhere in the rendering pipeline (Phase 04).
- Changing `syncDays` to be timezone-aware (Phase 03).
- A geocoded lookup for destinations not in the hand-curated table.
- Letting reservations override the trip-level timezone (they already have their own; this module does not touch them).
- A user-level "default destination timezone" preference beyond the existing `UserTravelPreference.home_timezone`.
