# Phase 04 - Display And Formatting

## Goal

Eliminate the `new Date('YYYY-MM-DD')` UTC-midnight bug everywhere it appears, and standardize every trip-date render through a single helper that formats a date-only string against the trip's destination timezone. After this phase ships, a trip with `starts_on = 2026-09-26` and `destination_timezone = 'Asia/Manila'` reads as "Sep 26, 2026" on every page, in every viewer's browser timezone.

This is the loudest user-facing fix in the module. Phase 03 made the data right; Phase 04 makes the screen right.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 02 (`destination_timezone` available on every trip payload) and Phase 03 (`day.label` + `day.kind` on every day payload)
- Blocker: none

## The Bug, Stated Precisely

`new Date('2026-09-26')` in any browser interprets the string as `2026-09-26T00:00:00Z` (UTC midnight), per the ECMAScript "Date Time String Format" spec for date-only inputs. `Intl.DateTimeFormat` with no `timeZone` option then formats that instant in the browser's local timezone. For a browser at `America/Los_Angeles` (UTC-7 in summer), UTC midnight on the 26th is 5pm on the 25th — and the formatter prints "Sep 25, 2026".

Fix: never pass a date-only string to `new Date()`. Construct a `Date` from year/month/day parts, or use `Intl.DateTimeFormat`'s `timeZone` option after parsing.

## New Helper

`resources/js/lib/dates.ts`:

```ts
/**
 * Parse a date-only string ('YYYY-MM-DD') as a calendar date in the given IANA
 * timezone, then format it. Never use new Date(value) on a date-only string —
 * that interprets it as UTC midnight and drifts by up to 24 hours depending on
 * the viewer's browser timezone.
 */
export function formatTripDate(
    value: string | null | undefined,
    timeZone: string,
    options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' },
): string {
    if (!value) {
        return 'Flexible';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) {
        // Not a date-only string — let the standard formatter handle it.
        return new Intl.DateTimeFormat(undefined, { ...options, timeZone }).format(new Date(value));
    }

    const [, y, m, d] = match;

    // Build a UTC instant at midnight of the target day. Then format in the
    // target tz with the same date parts — Intl will render whatever calendar
    // day that instant lands on in that tz, which is the day the user wrote.
    const utcMidnight = Date.UTC(Number(y), Number(m) - 1, Number(d));

    return new Intl.DateTimeFormat(undefined, { ...options, timeZone: 'UTC' }).format(new Date(utcMidnight));
}
```

The trick: build the Date from `Date.UTC(y, m - 1, d)` and format with `timeZone: 'UTC'`. Both halves of the conversion happen in UTC, so no DST or local-tz adjustment happens to the date itself. The `timeZone` parameter is kept on the signature for symmetry with `formatTripDateTime` (below) and for future use — Phase 04 does not actually consume it for date-only strings.

```ts
/**
 * Format a full datetime string against a specific IANA timezone. For
 * reservation / flight / lodging / itinerary-item rendering — the timezone
 * comes from the entity itself (e.g. reservation.starts_timezone).
 */
export function formatTripDateTime(
    value: string | null | undefined,
    timeZone?: string,
    options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' },
): string {
    if (!value) {
        return 'Time TBD';
    }

    return new Intl.DateTimeFormat(undefined, { ...options, timeZone: timeZone || undefined }).format(new Date(value));
}
```

`formatTripDateTime` is a renamed, central version of the existing `formatDateTime` lambda in `Trips/Show.vue:1048-1056`. Same behavior.

Server side companion: `app/Support/TripDates.php`:

```php
final class TripDates
{
    public static function formatDate(?string $value, string $timezone, string $format = 'M j, Y'): string
    {
        if (! $value) {
            return 'Flexible';
        }

        return Carbon::parse($value, $timezone)->translatedFormat($format);
    }

    public static function formatDateTime(?string $value, ?string $timezone, string $format = 'M j, g:i A'): string
    {
        if (! $value) {
            return 'Time TBD';
        }

        return Carbon::parse($value)->setTimezone($timezone ?? config('app.timezone'))->translatedFormat($format);
    }
}
```

Used by the Print view and the ICS / JSON exporters (Phase 05).

## Migration Surface

Replace every offending call site. Found via the Phase 01 audit (`audit_findings` filtered by `kind = render`). Expected sites:

| File | Pattern | Replace with |
| --- | --- | --- |
| `resources/js/pages/Trips/Show.vue` | `formatDate(value)` lambda (1044-1046) | Import + call `formatTripDate(value, trip.destination_timezone)` |
| `resources/js/pages/Trips/Show.vue` | `formatDateTime(value, timeZone)` lambda (1048-1056) | Import + call `formatTripDateTime` from helper |
| `resources/js/pages/Trips/Show.vue` | Every call site of those two lambdas | Update arguments to pass the timezone |
| `resources/js/pages/Trips/Index.vue` | `formatDate` lambda + call sites | Same |
| `resources/js/pages/Trips/Search.vue` | `formatDate` lambda + call sites | Same |
| `resources/js/pages/Trips/Print.vue` | `formatDate` lambda + call sites | Same |
| `resources/js/pages/Calendar/Index.vue` (or wherever the calendar lives) | Any date-only `new Date(...)` | Same |
| `resources/js/pages/Reminders/Index.vue` | Any trip-level date render | Same |
| `resources/js/components/**` | Any leftover `new Date(...)` on a date-only string | Same |

For each file, also delete the local lambda — there is exactly one helper.

## Hero Pair: destination time + home time

In the trip hero (`Trips/Show.vue` ~line 1064-1075), the date pair becomes a small two-row block when `destination_timezone !== home_timezone`:

```
Manila, Philippines · Sep 26 – Oct 5, 2026 · 11 days
in Manila time (PHT, UTC+8)
You leave Sep 25 in Los Angeles time (PDT, UTC-7)
```

For same-tz trips (the common case), only the first line shows. The second/third lines are conditional on `trip.destination_timezone !== trip.home_timezone`.

The "in {city} time" suffix is computed client-side via:

```ts
function timezoneLabel(tz: string): string {
    const parts = new Intl.DateTimeFormat(undefined, { timeZone: tz, timeZoneName: 'short' })
        .formatToParts(new Date());
    const short = parts.find(p => p.type === 'timeZoneName')?.value;
    const offsetParts = new Intl.DateTimeFormat(undefined, { timeZone: tz, timeZoneName: 'shortOffset' })
        .formatToParts(new Date());
    const offset = offsetParts.find(p => p.type === 'timeZoneName')?.value;
    return short && offset ? `${short}, ${offset}` : tz;
}
```

Lives in `resources/js/lib/dates.ts` alongside `formatTripDate`.

## Day card label

Phase 03 already added `day.label` to the payload. Phase 04 just wires the date half:

```vue
<CardTitle>
    <span>{{ day.label }}</span>
    <span class="text-muted-foreground"> · {{ formatTripDate(day.date, trip.destination_timezone) }}</span>
</CardTitle>
```

The earlier `// TODO Phase 04` from Phase 03 is removed.

## Tests

`resources/js/lib/__tests__/dates.spec.ts` (Vitest, if wired; otherwise a Pest browser test that asserts the rendered text):

- `formatTripDate('2026-09-26', 'Asia/Manila')` returns "Sep 26, 2026".
- `formatTripDate('2026-09-26', 'America/Los_Angeles')` returns "Sep 26, 2026" — the helper does not shift the calendar day regardless of timezone argument.
- `formatTripDate(null, 'Asia/Manila')` returns "Flexible".
- `formatTripDate('2026-03-09', 'America/Los_Angeles')` (US DST spring-forward day) returns "Mar 9, 2026" — no off-by-one.

If `npm run test:unit` isn't already configured, add a Pest browser test in `tests/Browser/TripDateRenderingTest.php` that loads a Manila trip with a known `starts_on`, sets the browser timezone via `pwsh` `$env:TZ` (won't work — browsers ignore that), so instead override `Intl.DateTimeFormat.prototype.resolvedOptions` in a test setup script to lie about the local timezone. Pest 4 browser tests support `evaluateJs`.

`tests/Feature/Trips/TripDateFormatPayloadTest.php`:

- Asserts the trip detail payload includes `destination_timezone` and that the day list has `date`, `kind`, `label` for every day.
- Regression check that reservations still serialize with their own timezones (they should — this phase doesn't touch them).

## Manual Verification

For the Manila example trip:

1. Set browser tz to `America/Los_Angeles`. Load `/trips/{id}`. Confirm hero reads "Sep 26 – Oct 5, 2026" exactly. Confirm first day card reads "Travel · departure · Sep 26", second reads "Arrival · Day 1 · Sep 27".
2. Switch browser tz to `Asia/Manila`. Reload. All renders identical.
3. Switch to `UTC`. Reload. Identical.

For a same-tz trip (LA → SF):

1. Hero shows only the single date pair line (no "in Manila time" suffix).
2. Days are "Day 1 · Sep 12", "Day 2 · Sep 13", etc.

## Acceptance Criteria

- Exactly one `formatTripDate` and one `formatTripDateTime` exported from `resources/js/lib/dates.ts`. No surviving per-page lambdas.
- `grep -nR "new Date(" resources/js/pages | grep -E "starts_on|ends_on|\.date\b"` returns zero hits.
- Manila example renders "Sep 26" everywhere in all three test browser timezones.
- `npm run types:check` and `npm run build` pass.
- All Phase 04 tests pass.
- `vendor/bin/pint --dirty --format agent` clean (a small PHP helper was added).
- No regression: reservation rendering still respects `reservation.starts_timezone` etc., since `formatTripDateTime` keeps the existing argument shape.

## Out Of Scope

- Calendar / ICS / JSON / Print formatting on the server side (Phase 05).
- Wholesale rewrite of the reservation card date strings (already correct via per-leg timezones).
- A user preference for date format (M j, Y vs ISO).
- Localization beyond what `Intl.DateTimeFormat` already provides.
- Animation when the hero label changes after anchor reassignment.
