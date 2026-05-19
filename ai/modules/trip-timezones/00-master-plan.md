# Trip Timezones Master Plan

## Goal

Make trip dates honest about timezones. Today the app treats `trips.starts_on` and `trips.ends_on` as plain calendar dates with no notion of which timezone they belong to, and the frontend then re-parses those strings through `new Date()` (which interprets `YYYY-MM-DD` as UTC midnight). The result: a US-based user planning a Philippines trip sees the start date drift by one day on screen, "Day 1" is labeled against the home/US calendar instead of the day they actually land in Manila, and there is no way to express the travel day that crosses the international dateline.

This module fixes the data model, the day-numbering logic, the display layer, and every derivative (calendar, ICS, reminders, imports) so that trip dates always mean "this calendar date in the destination's local time," and travel days that span timezones are represented honestly instead of silently collapsed.

## Status

- Status: `planned`
- State file: `ai/state/trip-timezones.json`
- Last updated: 2026-05-18

## Concrete Symptom The User Reported

> "My trip 'starts' on the 25th, which I don't have the ability to edit currently, and when I leave on the 26th, I will arrive in Manila on the 27th. That will be day one."

Decoded:

- The trip is stored with `starts_on = 2026-09-25` (or similar — the user can't edit it, so they're reading what the UI shows).
- Their actual departure from the US is on the 26th (local US time).
- They land in Manila on the 27th (local Manila time, PHT/UTC+8).
- They want **Day 1 = the day they arrive in Manila**, not whichever calendar date happens to fall first in their home timezone.

Two failures are stacked here:

1. **Display drift.** `formatDate('2026-09-26')` in `resources/js/pages/Trips/Show.vue:1044-1046` calls `new Date('2026-09-26')` — ISO date-only strings are parsed as UTC midnight, then `Intl.DateTimeFormat` renders them in the browser's local timezone. From any US timezone (UTC-4 through UTC-10), that midnight UTC lands on the *prior* calendar day. So `starts_on = 2026-09-26` renders as "Sep 25" on the user's screen, and they have no way to fix it. This is the "I don't have the ability to edit currently" half of the complaint.
2. **Semantic gap.** Even if the display were fixed, the trip has no `destination_timezone` and no concept of a travel day. `Trip::syncDays()` in `app/Models/Trip.php:130-155` just iterates from `starts_on` to `ends_on` calling every day "Day 1", "Day 2", etc. There is no way to say "the trip begins on the 26th (departure, travel day) but Day 1 of the actual vacation is the 27th in Manila time."

## Context Of Prior Work

- `phase-03-reservations-dates-and-times.md` (verified) — reservations, flight segments, and lodging stays already store paired `*_at` datetimes and `*_timezone` strings (e.g. `starts_at` + `starts_timezone`, `departs_at` + `departure_timezone`, `arrives_at` + `arrival_timezone`). So the reservation layer is largely timezone-correct already. The hole is the trip envelope itself.
- `editable-trip-entries` (verified) — added in-place editing for most child entities. The trip's `starts_on` / `ends_on` are editable via the trip edit page, but the user's complaint hints that the relevant control isn't discoverable or doesn't behave as expected. Worth confirming during audit.
- `themes-plan`, `responsive-ui-uplift`, `navigation-uplift`, `scroll-issue`, `mobile-ui-ux` — irrelevant to timezone correctness; mentioned only so this module doesn't collide with their state files.

## Problem (Detailed)

### Schema gaps

- `trips.starts_on` and `trips.ends_on` are `date` columns. No timezone is attached. No `home_timezone` or `destination_timezone` exists on the trip.
- `Trip::syncDays()` (`app/Models/Trip.php:130-155`) iterates the date range in raw string form and labels every generated `trip_day` "Day 1", "Day 2", … against position. There is no concept of a travel day, an arrival day, or a departure day.
- `Trip::tripLengthLabel()` and `Trip::timingBucket()` use `starts_on->diffInDays(ends_on)` and `lt(now())` / `gte(now())` — both compare a date-cast Carbon (interpreted in the app's default `config('app.timezone')`, which is `UTC` unless changed) against `now()` in the same default timezone. A trip "active right now" check from a US user looking at a Manila trip will be off by up to a day at boundaries.

### Display gaps

- `formatDate` (`resources/js/pages/Trips/Show.vue:1044-1046`) parses `YYYY-MM-DD` via `new Date(...)`. This is the JS date-only UTC-midnight gotcha. Same pattern appears in `Trips/Index.vue`, `Trips/Search.vue`, `Trips/Print.vue` — needs grep + fix everywhere.
- `formatDateTime` (`Show.vue:1048-1056`) correctly takes a `timeZone` argument for reservation rendering, but trip-envelope dates have no timezone to pass.
- `trip.length` is rendered from the server (`Trip::tripLengthLabel`) and depends on the broken bucket logic.

### Derivatives

- `TripExportService` (ICS / JSON export) — uses the same untyped dates.
- `CalendarController` — likely buckets entries by raw date strings; needs to honor destination timezone for cross-tz trips.
- `TripImportParser` — when importing a confirmation, the inferred trip date may be in the email sender's timezone (e.g. an airline confirmation in UTC) and is currently stored as-is.
- `TripReminder` already has its own `timezone` column, so reminders should be unaffected once the trip envelope is fixed — confirm during audit.

## Strategy

Five phases, in order. Each is gated on the previous so we don't ship a half-converted system.

1. **Audit + ground truth.** Phase 01 — no code changes. Walk every read and write of `starts_on`, `ends_on`, `syncDays`, `tripLengthLabel`, `timingBucket`, `formatDate`. Catalog them in `ai/state/trip-timezones.json` under `audit_findings`. Confirm whether the trip edit UI lets the user change dates (the user said they "don't have the ability to edit currently" — find out if that's a missing control or a perception issue from the display drift). Capture screenshots of the current Philippines-trip view at the user's actual browser timezone for before/after.
2. **Schema: destination timezone on the trip.** Phase 02 — add `destination_timezone` (nullable, IANA name, e.g. `Asia/Manila`) and `home_timezone` (nullable, falls back to the owner's `UserTravelPreference.home_timezone` or `config('app.timezone')`) to `trips`. Wire into the trip create / edit form with a searchable timezone picker prepopulated from common destinations. Backfill existing trips by inferring from `destination` text (best-effort lookup table for the dozen most common destinations; default to `UTC` and surface a one-time prompt on the trip detail screen otherwise).
3. **Day numbering, travel days, and arrival/departure semantics.** Phase 03 — rewrite `Trip::syncDays()` so that:
   - The day range is generated in the **destination timezone** (so "Sep 27" in Manila means the 24-hour window 2026-09-27T00:00+08:00 .. 2026-09-28T00:00+08:00, regardless of where the viewer is).
   - Each `trip_day` carries a `kind` of `travel-out`, `arrival`, `vacation`, `departure`, or `travel-home` (default `vacation` for a same-timezone trip).
   - The "Day N" label is computed from the first `arrival` (or `vacation`) day, not from `starts_on`. The travel day shows as "Travel · Sep 26 → Sep 27" or similar.
   - Allow the user to manually mark a `trip_day` as the explicit Day 1 if the heuristic gets it wrong.
4. **Display layer: kill the UTC-midnight bug everywhere.** Phase 04 — replace every `new Date(yyyymmdd)` with a helper that parses a date-only string as the trip's destination-local date (not UTC). Update `formatDate`, `formatDateTime`, hero summaries, day card titles, the `trip.length` label, the Calendar page, the Print view, the Search results, and the ICS / JSON exports. Single helper in `resources/js/lib/dates.ts` and a matching Carbon helper in `app/Support/TripDates.php`. Add a small "destination time · home time" pair under the hero so users see both at a glance.
5. **Derivatives + verification.** Phase 05 — make sure every consumer of trip dates uses the new helpers and respects the destination timezone: `CalendarController`, `TripExportService` (ICS), `TripImportParser` (when inferring a trip start from an imported confirmation), `TripAutomationService` suggestions, `ReminderInboxController`, notification deep links, and Search. Verification matrix: a Philippines trip from PST, a London trip from EST, a same-timezone trip (regression check), and a trip that spans a DST transition.

## Phases

1. [Audit And Ground Truth](01-audit-and-ground-truth.md)
2. [Schema And Timezone Fields](02-schema-and-timezone-fields.md)
3. [Day Numbering And Travel Days](03-day-numbering-and-travel-days.md)
4. [Display And Formatting](04-display-and-formatting.md)
5. [Derivatives And Verification](05-derivatives-and-verification.md)

## Implementation Order

Strictly sequential. Phase 01 freezes the catalog of broken sites; Phase 02 lands the new columns and form control; Phase 03 makes day generation timezone-aware (and depends on the new columns); Phase 04 fixes the display layer (and depends on the new "Day N" semantics so the labels don't churn twice); Phase 05 rolls the new helpers through every derivative.

## Decisions Baked In (Override In Phase 01 If You Disagree)

| Decision | Rationale |
| --- | --- |
| **The trip's `destination_timezone` is the authoritative timezone for all trip-envelope dates** (start, end, day labels, length, "active now"). Reservation timezones are still per-leg (departure airport tz, arrival airport tz). | A trip is a stay in one place; per-leg datetimes already handle the in-transit pieces. |
| **`home_timezone` is informational only**, used for the hero's "you leave home on X" line and for the travel-day rendering. Day numbering does not depend on it. | Day 1 of a Philippines trip is in Manila regardless of where the user lives. |
| **`trip_days.kind` enum is `vacation` by default**; same-timezone trips never see the other values. | Zero behavior change for domestic trips. |
| **`starts_on` and `ends_on` keep their `date` type**, but their meaning becomes "the local calendar date in `destination_timezone`." | Avoids a column rename / wide migration; semantics shift, the column stays. |
| **Date strings on the wire stay `YYYY-MM-DD`**; the frontend never parses them as Date objects — it formats them via the new `formatTripDate(value, tz)` helper that constructs a Date from year/month/day parts in the target timezone. | Removes the entire class of UTC-midnight bugs. |
| **No automatic IANA timezone lookup from `destination` text in Phase 02.** Phase 02 ships a hand-curated table for the top ~30 destinations and falls back to a picker prompt. | A geocode-based lookup is a separate effort; this module is about correctness, not destination intelligence. |
| **The "Day 1 override"** in Phase 03 is a per-trip-day boolean flag — the user can mark any day as "this is Day 1 of my vacation" and the surrounding days renumber. | Heuristic-based numbering will get it wrong sometimes; a single-click override is enough. |
| **No timezone changes to reservations, flight segments, lodging stays, or reminders.** Their timezone handling is already correct (Phase 03 of the original build). | This module is scoped to the trip envelope. |
| **No backfill data migration that guesses destination_timezone from `destination` for existing trips beyond the hand-curated table.** Existing trips with unrecognized destinations get a one-time inline prompt on the detail screen. | A wrong guess is worse than asking. |

## Acceptance Criteria

- Audit document (Phase 01) lists every read/write site of `starts_on`, `ends_on`, and the day-numbering / formatting helpers, plus a confirmed answer to "can the user actually edit the dates today?"
- A `trips.destination_timezone` (IANA string, nullable initially, required for new trips after Phase 02 ships) exists and is set via a searchable picker on trip create + edit.
- `Trip::syncDays()` generates days in destination-local time. Days have a `kind` (`vacation` default) and the "Day N" label is derived from the first `vacation` day, not row position.
- For the user's Manila example: `starts_on = 2026-09-26` (US departure date), `ends_on` = whatever the return-to-home date is in Manila time. With `destination_timezone = Asia/Manila` and the `2026-09-26` day marked `travel-out`, the trip card shows "Travel · Sep 26 → Sep 27", and the first "Day 1" label sits on `2026-09-27`. Editing the trip's start date from the hero is one tap away.
- `formatDate('2026-09-26', 'Asia/Manila')` in the frontend renders as "Sep 26, 2026" regardless of the user's browser timezone. Asserted with a Vitest unit test against at least three browser-tz simulations (PST, EST, UTC).
- `php artisan test --compact` covers: schema migration, picker validation, `syncDays` for same-tz / cross-tz / DST-crossing trips, `timingBucket` against a destination-tz `now()`, and ICS export emits destination-tz `DTSTART` / `DTEND`.
- All standard gates pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`.
- Manual matrix in Phase 05: PST user planning Manila, EST user planning London, same-tz user planning a domestic trip (regression), trip crossing a DST transition.

## Out Of Scope

- Geocoding `destination` text to an IANA timezone (requires a provider; separate module).
- Per-day timezone (a road trip across timezones) — Phase 03 supports per-day `kind` but not per-day `timezone`. Reservation legs still carry their own.
- A user-facing "home timezone" setting beyond what `UserTravelPreference.home_timezone` already provides (already shipped).
- Backfilling reservations / flight segments to fix bad imported timezones (their schema is already correct; data hygiene is a separate effort).
- Changing how the calendar UI groups events when a single event straddles midnight in two zones (cosmetic; defer).
- Timezone-aware notifications (the reminder timezone column is already there; this module doesn't touch reminder delivery).
- Multi-leg "trip itineraries" that visit several destinations with different timezones — out of scope; modeled as multiple trips today, and that stays.
