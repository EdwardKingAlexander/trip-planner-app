# Phase 05 - Derivatives And Verification

## Goal

Roll the new `destination_timezone` semantics and the `TripDates::formatDate` / `formatTripDate` helpers through every consumer of trip-level dates: ICS export, JSON export, Print view, Calendar page, Search results, Reminders inbox, notification deep links, imports, and the automation suggestion engine. Then verify end-to-end against the matrix in the master plan and gate on the standard checks.

This phase is mechanical. The hard thinking happened in 02–04. The job here is to make sure nothing renders or exports a trip date in the wrong frame.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 02 / 03 / 04
- Blocker: none

## Consumers To Update

For each, the rule is the same: replace any direct `starts_on->format(...)` or `Carbon::parse(date)` that ignored the trip's destination timezone with a call through `TripDates::formatDate(value, $trip->effectiveDestinationTimezone())` (or `formatTripDate` on the frontend).

### ICS export (`app/Services/TripExportService.php`)

- `DTSTART;VALUE=DATE:` and `DTEND;VALUE=DATE:` for the trip block must emit the destination-tz local dates. RFC 5545 `VALUE=DATE` is timezone-agnostic — what matters is that we emit the dates the user wrote, not the UTC-equivalent shifted dates.
- Per-event lines for itinerary items and reservations already use their own per-leg timezones — verify no regression.
- Add a small `X-WR-TIMEZONE` calendar property set to the trip's destination timezone (informational; not load-bearing).

### JSON export

- Trip block adds `destination_timezone` and `home_timezone` to the top level.
- Each day in the day list serializes `kind`, `label`, `is_day_one_anchor`.
- Date strings stay `YYYY-MM-DD` — consumers can parse them as destination-local dates using the included timezone field.

### Print view (`resources/js/pages/Trips/Print.vue`)

- All `formatDate` calls swap to `formatTripDate(value, trip.destination_timezone)`.
- The print header gets the same destination/home tz line as the hero (when they differ).
- Day pages use the new `day.label` instead of `day.title`.

### Calendar page (`app/Http/Controllers/CalendarController.php` + `resources/js/pages/Calendar/Index.vue`)

- Server: trip range is bucketed by destination-tz calendar dates, not raw `starts_on`. The cell a trip is placed in depends on whether the destination-tz date falls in that month's grid as currently rendered.
- Frontend: any `new Date(trip.starts_on)` is replaced with `formatTripDate(...)` for label rendering. Range comparison logic uses the same year/month/day-parts approach to avoid timezone drift.
- Edge case: a trip whose destination-tz date is in a different month than its UTC date (cross-month drift) — the trip pin lands in the destination-tz month, with a small badge "+1d from home time" when the home-tz date differs.

### Search (`resources/js/pages/Trips/Search.vue`)

- Result cards swap to `formatTripDate`.
- Server-side `Trip::scopeFilterByDate` (if it exists) is reviewed for raw-date comparisons. If trip-date filters use the user's submitted dates against `starts_on` in UTC, they need to compare with the destination-tz dates instead — but in practice, since both sides are date-only strings, a string comparison is equivalent and no change is required. Confirm during the phase.

### Reminders inbox (`app/Http/Controllers/ReminderInboxController.php`)

- Reminders already store their own `timezone` (per the original Phase 03). Verify the inbox groups them correctly when the trip's destination tz differs from `config('app.timezone')`. Add a regression test.

### Notification deep links

- The `notification-deep-links` module's `NotificationDeepLinkResolver` doesn't render dates, so likely nothing to do. Confirm by grepping for date-only renders in the resolver and the notification list page.

### Imports (`app/Services/TripImportParser.php`)

- When a confirmation parse infers a trip start/end (e.g. from a flight confirmation), the parser currently writes raw datetimes in the sender's frame. After this phase, the parser sets `destination_timezone` on the trip if it can be inferred from the arrival airport (using the same airport→tz table from Phase 03). If unknown, the parser leaves the trip's `destination_timezone` null and the inline backfill banner (Phase 02) surfaces on first load.
- No change to reservation-level imports — they remain per-leg.

### Automation suggestions (`app/Services/TripAutomationService.php`)

- Suggestions that "you arrive on Sep 27 — consider packing a power adapter for the Philippines" need the destination-tz arrival date, not the home-tz one. Update the date inputs to the suggestion templates accordingly.

## Tests

`tests/Feature/Trips/TripExportTimezoneTest.php`:

- ICS export of the Manila example: `DTSTART;VALUE=DATE:20260926` (the date the user wrote), `DTEND;VALUE=DATE:20261006` (DTEND is exclusive). `X-WR-TIMEZONE:Asia/Manila` present.
- ICS export of a same-tz trip: no `X-WR-TIMEZONE` or set to `home_timezone` (decision: emit it always, matching `destination_timezone` even if equal to home, for downstream consumer clarity).
- JSON export includes `destination_timezone`, `home_timezone`, `days[*].kind`, `days[*].label`.

`tests/Feature/Calendar/CalendarTimezoneTest.php`:

- A Manila trip with `starts_on = 2026-09-26` renders in the September grid cell for the 26th regardless of the viewing user's preference.
- Reminders attached to the trip render in their own timezones (regression).

`tests/Feature/Imports/ImportInfersDestinationTimezoneTest.php`:

- Parse a fixture LAX→MNL confirmation. Assert the created trip's `destination_timezone = 'Asia/Manila'`.
- Parse a fixture confirmation for an unknown destination. Assert `destination_timezone` is null and the trip surfaces the banner.

`tests/Browser/TripDateMatrixTest.php` (Pest 4):

- For each of three browser-tz overrides (PST, EST, UTC), load the Manila trip and assert:
  - Hero reads "Sep 26 – Oct 5, 2026".
  - First day card reads "Travel · departure · Sep 26".
  - Second day card reads "Arrival · Day 1 · Sep 27".
- Load a same-tz trip (LA → SF) and assert the hero has no second tz line and days are "Day 1", "Day 2", …

## Manual Verification Matrix

| Scenario | Browser TZ | Trip | Expected |
| --- | --- | --- | --- |
| The reported bug | `America/Los_Angeles` | Manila, `starts_on = 2026-09-26`, dest `Asia/Manila`, with LAX→MNL flight | Hero "Sep 26 – Oct 5". Day 1 = 2026-09-27. ICS DTSTART 20260926. |
| Mirror check | `Asia/Manila` | Same trip | Identical to above. |
| EU outbound | `America/New_York` | London, `starts_on = 2026-12-10`, dest `Europe/London`, with JFK→LHR overnight | Hero "Dec 10 – Dec 17". 12-10 = travel-out, 12-11 = arrival/Day 1. |
| Same tz | `America/Los_Angeles` | San Francisco, `starts_on = 2026-08-01`, dest `America/Los_Angeles` | Single hero line. Days "Day 1 · Aug 1" … (regression check). |
| DST transition | `America/Los_Angeles` | London, dates spanning UK DST end (last Sunday of October) | Day count correct; no missing/duplicate day. |
| Crosses month at destination | `America/Los_Angeles` | Sydney, `starts_on = 2026-06-30`, dest `Australia/Sydney` | Hero "Jun 30 – ...". Calendar page renders the trip pin on the 30th. |
| Unknown destination | `America/Los_Angeles` | "Some Tiny Town", `destination_timezone = null` | Backfill banner visible. Hero reads dates as-written. Day labels "Day 1" etc. (heuristic falls back to vacation kind). |

## Acceptance Criteria

- ICS, JSON, Print, Calendar, Search, Reminders, Imports, and Automation all read trip dates through `TripDates::formatDate` / `formatTripDate` (or their explicit helpers).
- `grep -nR "starts_on->format\|ends_on->format\|new Date(.*starts_on\|new Date(.*ends_on" app resources/js` returns zero hits.
- All scenarios in the manual matrix produce the expected output. Screenshots stored under `ai/audit/trip-timezones-2026-05-18/verification/`.
- `php artisan test --compact` is green.
- `npm run lint:check`, `npm run types:check`, `npm run build` all pass.
- `vendor/bin/pint --dirty --format agent` clean.
- `STATE.md` updated to reflect this module's completion.

## Release Notes (for the trip activity log / changelog)

When this module ships, the user-facing entry is:

> Trips now know which timezone they're in. The dates you write are the dates you see — no more drift when planning international trips from a US browser. Travel days are labeled honestly, and you can mark any day as "Day 1" of your vacation.

## Out Of Scope

- Calendar UI overhaul (only the bucketing logic + label formatting changes).
- A per-user "show all trip dates in my home tz" toggle (could be a follow-up if anyone asks).
- Backfilling reservation-level timezones for trips that were imported before this module shipped (reservation tz is already correct from the original Phase 03; bad data is a one-off cleanup, not a module).
- Time-travel testing infrastructure beyond Pest 4's browser-tz override.
- Cross-trip "stack two trips end-to-end and renumber" support.
