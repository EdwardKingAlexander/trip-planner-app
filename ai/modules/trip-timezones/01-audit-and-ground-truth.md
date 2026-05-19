# Phase 01 - Audit And Ground Truth

## Goal

Catalog every place trip dates are stored, derived, or rendered, and confirm the user-visible behavior on their actual Philippines trip. No code changes. Output is a structured finding list in `ai/state/trip-timezones.json` under `audit_findings`, plus a one-paragraph reproduction of the reported bug.

Without this, later phases risk fixing the loud symptom (display drift) while missing quieter bugs (e.g. ICS export, calendar bucketing, `timingBucket` boundary errors).

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: none
- Blocker: none

## Audit Inputs

- The live app at `http://vacation-plan-app.test` (Herd).
- The user's existing Philippines trip (or a fresh trip created with `destination = "Manila, Philippines"`, `starts_on = 2026-09-26`, `ends_on = 2026-10-05` for repro).
- Browser DevTools with the **Sensors panel** set to a US timezone (e.g. `America/Los_Angeles`) to reproduce the drift. A second pass at `Asia/Manila` confirms how it should look.
- Codebase. Specific paths likely involved:
  - `app/Models/Trip.php` — `syncDays`, `tripLengthLabel`, `timingBucket`, casts.
  - `app/Http/Controllers/TripController.php` — validation, payload shape, summary methods.
  - `app/Services/TripExportService.php` — ICS / JSON.
  - `app/Http/Controllers/CalendarController.php` — calendar bucketing.
  - `app/Http/Controllers/TripImportController.php`, `app/Services/TripImportParser.php` — inferred trip dates from imports.
  - `app/Models/UserTravelPreference.php` — existing `home_timezone` field, if any.
  - `resources/js/pages/Trips/Show.vue`, `Trips/Index.vue`, `Trips/Search.vue`, `Trips/Print.vue` — `formatDate`, hero, day cards.
  - `resources/js/pages/Calendar/*`, `resources/js/pages/Reminders/*` if they read trip-level dates.

## Audit Method

Two passes:

### Pass A: code inventory

For every match of these patterns, record a finding:

- `starts_on`, `ends_on` (PHP + Vue).
- `syncDays`, `tripLengthLabel`, `timingBucket`.
- `formatDate(`, `formatDateTime(` (Vue).
- `new Date(` where the argument is a date-only string (`YYYY-MM-DD`) — the bug class.
- `Carbon::parse(` on a date-only string in a controller / service that affects trip dates.
- Any direct read of `trip.days[*].date` on the frontend (`Show.vue`, calendar, print).

A finding is one row in `audit_findings` with: `id`, `kind` (`store`, `compute`, `render`, `export`, `import`), `file_path`, `line_range`, `summary`, `proposed_fix_phase` (02 / 03 / 04 / 05), `severity` (`critical`, `high`, `medium`, `low`).

### Pass B: live reproduction

Walk the live app at two browser timezones (LA + Manila) for the same trip and record what the user sees:

1. Trip hero (`/trips/{id}`): does the date pair render the same in both browser timezones? Screenshot both.
2. Day cards: what's the label and the date on the first day?
3. Trip card on `/trips`: what's the date pair?
4. Trip print view: what's the date pair?
5. Calendar (`/calendar`): which day cell is the trip rendered on?
6. ICS export: open the file, read `DTSTART;VALUE=DATE` — does it match what the user wrote?
7. Trip edit form: is there actually a date control? Can the user change `starts_on`? (Answers the user's "I don't have the ability to edit currently" remark — find out whether it's missing, hidden, or just looks wrong because of display drift.)

Each step gets a finding row.

## Mandatory Walks

### Walk 1: The reported bug

Reproduce, with screenshots:

- Create `destination = "Manila, Philippines"`, `starts_on = 2026-09-26`, `ends_on = 2026-10-05`.
- At browser tz `America/Los_Angeles`: capture the trip card on `/trips`, the hero on `/trips/{id}`, the first day card.
- At browser tz `Asia/Manila`: capture the same three views.
- Confirm: does `starts_on` render as Sep 25 in LA, Sep 26 in Manila? (Expected, based on `new Date('2026-09-26')` parsing as UTC midnight.)

### Walk 2: Day numbering

- Look at the day cards on the trip. What does the title field hold for the first day? Does `Trip::syncDays()` actually call it "Day 1"? Confirm.
- Is the first day's `date` the US-departure day (26th) or the Manila-arrival day (27th)? Both are wrong, but in different ways — record which.

### Walk 3: Edit affordance

- Open the trip edit form (or the edit affordance, wherever it is).
- Confirm whether `starts_on` and `ends_on` are editable.
- Note the input type (`<input type="date">` vs custom picker).
- Try changing `starts_on` from 26th to 27th. Does the day list regenerate? Does Day 1 move?

### Walk 4: `timingBucket` and `tripLengthLabel`

- Use `php artisan tinker --execute 'App\Models\Trip::find(X)->timingBucket();'` with the trip's id.
- Compare to the user's intuition for "is this trip active right now."
- Same for `tripLengthLabel()` — does "11 days" match the user's mental count for a trip that includes a travel day?

### Walk 5: Derivatives

- `/calendar`: where is the trip rendered? On the 25th, 26th, or 27th cell?
- `/trips/{id}/export.ics`: open and inspect.
- `/trips/{id}/export.json`: inspect for raw date strings.
- `/trips/{id}/print`: inspect the printed hero and day list.
- `/trips/search?query=Manila`: inspect the search result card's date pair.
- Reminders inbox (`/reminders`): is anything mis-bucketed for the Manila trip?

### Walk 6: Imports

- If the user has imported any confirmation for this trip, inspect the resulting reservation `starts_at` / `starts_timezone`. (Reservations are already timezone-aware, so this is a regression check, not a fix scope.)
- Inspect any `TripAutomationSuggestion` records for the trip — are their suggested dates in destination-tz or wall-clock-of-source?

## Output Shape (frozen)

Append to `ai/state/trip-timezones.json`:

```json
"audit_findings": [
    {
        "id": "tz-001",
        "kind": "render",
        "file_path": "resources/js/pages/Trips/Show.vue",
        "line_range": "1044-1046",
        "summary": "formatDate parses 'YYYY-MM-DD' via new Date(), which is UTC midnight; in any US tz the date renders one day earlier.",
        "proposed_fix_phase": "04",
        "severity": "critical",
        "evidence": "ai/audit/trip-timezones-2026-05-18/manila-hero-LA.png; ai/audit/trip-timezones-2026-05-18/manila-hero-Manila.png"
    },
    {
        "id": "tz-002",
        "kind": "compute",
        "file_path": "app/Models/Trip.php",
        "line_range": "130-155",
        "summary": "syncDays iterates raw date strings, labels every day 'Day 1', 'Day 2'; no concept of travel day or destination-local day.",
        "proposed_fix_phase": "03",
        "severity": "high",
        "evidence": "ai/audit/trip-timezones-2026-05-18/manila-day-cards-LA.png"
    }
]
```

Screenshots live under `ai/audit/trip-timezones-2026-05-18/`. Phase 02–05 owners filter `audit_findings` by `proposed_fix_phase`.

## Severity Triage Rules

- `critical` — wrong data shown to the user (display drift; ICS dates an entire day off; calendar bucket wrong).
- `high` — semantic gap that prevents the user from expressing what they mean (no travel-day concept; no editable timezone).
- `medium` — boundary cases (DST transitions, `timingBucket` off by a day at midnight).
- `low` — cosmetic (e.g. "Day 1" label vs. "Travel Day · Day 1" wording).

Phase 05 verification asserts every `critical` and `high` is fixed.

## Acceptance Criteria

- `audit_findings` contains at least one entry per Walk and one per code site found in Pass A. Minimum ~15 entries; the bug surface is small but real.
- Every finding has all required fields populated.
- Reproduction screenshots exist for Walks 1, 2, 3, and 5 in `ai/audit/trip-timezones-2026-05-18/`.
- A one-paragraph **reproduction note** is written into the state file's `repro` field, naming the exact trip id, the two browser timezones used, and the concrete observed-vs-expected for each view.
- The "can the user edit the dates" question has a definitive yes/no answer (with a screenshot of the relevant control or a note that no control exists).

## Out Of Scope

- Implementing fixes (Phases 02–05).
- Adding new timezone columns (Phase 02).
- Designing the picker UI (Phase 02).
- Writing tests (Phase 05).
