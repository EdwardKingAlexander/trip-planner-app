# Reservation Create Master Plan

## Goal

Make the Add Reservation form work, and when it doesn't, tell the user why. The current form on `Trips/Show.vue` only renders a single `InputError` (for `title`), while the server validates 30+ fields. Any validation failure on a non-title field — a mistyped freeform timezone, an `ends_at` earlier than `starts_at`, a bad email, a too-long string, anything — produces a silent 422: Inertia preserves the scroll, the form does not reset, the page does not change, and the user concludes "I cannot make another reservation."

This module reproduces the silent-failure surface, wires field-level errors across the entire add form, replaces the freeform timezone inputs with a real picker so the most common 422 path goes away, hardens the validator and the redirect/feedback contract, and locks the fix in with feature + browser tests.

## Status

- Status: `planned`
- State file: `ai/state/reservation-create.json`
- Last updated: 2026-05-18

## Concrete Symptom The User Reported

> "I have an issue where I cannot make another reservation."

Decoded from a read of the create flow:

- `app/Http/Controllers/TripReservationController.php::validatedReservation` (lines 83-127) declares ~30 rules.
- `resources/js/pages/Trips/Show.vue::reservationForm` (lines 233-254) collects 19 of those fields. Three more (`status`, `type`, `flight_details`) have defaults or are skipped.
- The add-reservation `<form>` block (lines 1588-1626) renders **one** `InputError` — line 1598, bound to `reservationForm.errors.title`. Every other server-side rule fails into a void.
- `post(form, url, resetFields)` (line 551-556) calls `form.post(url, { preserveScroll: true, onSuccess: () => form.reset(...) })`. On a 422 there is no `onSuccess` — `form.reset()` does not run, the page does not scroll, and there is no global flash for validation errors. From the user's seat: click, nothing happens, nothing happens, nothing happens.

Most likely concrete trigger for "I can't make another reservation":

1. **Freeform timezone Input rejected by PHP's `timezone` validator.** Lines 1606-1607 render `<Input v-model="reservationForm.starts_timezone">` and `…ends_timezone`. Default value is `Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'` (line 201). If the user types over it (e.g. "PST", "Asia/Manilla", "EST"), or if the browser returns a name PHP's `timezone_identifiers_list` doesn't accept (rare but real for legacy aliases on older PHP/IANA bundles), validation fails. No error renders.
2. **`ends_at` before `starts_at`.** `'ends_at' => [..., 'after_or_equal:starts_at']`. Silent 422.
3. **`contact_email` typo.** `'nullable', 'email'`. Silent 422.
4. **`booking_reference` > 120 chars.** A pasted long string from an airline confirmation. Silent 422.
5. **The form is gated behind something the user can no longer reach.** Lower probability, worth confirming.

Each is invisible because the form has no field-level error rendering except `title`.

## Context Of Prior Work

- `editable-trip-entries` (verified) — added in-place edit forms for all child entities. The edit form (lines 1276-1411) renders `InputError` for the same single field; it shares the silent-failure DNA. Phase 02 fixes both.
- `flight-details` (verified) — added the `flight_details` array under reservations. The Add form does **not** include flight_details fields, which is fine (the field is `nullable, array`), but the edit form does. No impact on create.
- `document-uploads` (verified) — added the per-reservation attachments expansion. Lives on display, not on create. Out of scope.
- `live-trip-collaboration` (verified) — emits a `reservation.created` activity event after a successful store. Unaffected — the controller already calls `$events->record(...)`. Confirm during Phase 05.
- `trip-timezones` (planned, this branch) — if shipped first, the trip will carry `destination_timezone` and `home_timezone`, and Phase 03 can default the reservation timezone pickers to those. If Phase 03 of this module ships before `trip-timezones`, fall back to the user's browser timezone. The two modules do not block each other.

## Problem (Detailed)

### Feedback gap (the user-visible bug)

- `Trips/Show.vue:1588-1626` — add form has one `InputError`. Every server rule beyond `title` fails into silence.
- The shared `post(form, url, resetFields)` helper does not scroll to errors, does not surface a top-of-form summary, and does not flash a global toast on 422.
- Inertia's default behavior on 422 — populate `form.errors` and `usePage().props.errors` — works correctly. The bug is downstream: the template never reads from `reservationForm.errors.<anything-but-title>`.

### Validation footguns (the real failure paths)

- `starts_timezone` / `ends_timezone` validated as `required|timezone`. The frontend control is a freeform text Input. Mismatch between what the browser produces and what PHP accepts is unavoidable without a constrained picker.
- `ends_at` / `starts_at` are `datetime-local` inputs (lines 1602-1603) but their timezone is whatever the user typed in the timezone fields. If the user picks `starts_at = 2026-09-26T14:00` with `starts_timezone = "Asia/Manila"` and `ends_at = 2026-09-26T18:00` with `ends_timezone = "America/Los_Angeles"`, the `after_or_equal` rule compares as naive strings — the PHT value will be "after" the PDT value as a string but should be earlier in real time. The rule is on the wrong abstraction. Either compare against UTC after applying each timezone, or document that this rule is wall-clock only and warn the user.
- `flight_details` is not submitted by the add form at all — the form has no flight_details inputs. For a `type=flight` reservation, the user creates the row with no flight details and then has to edit to add them. That's a UX gap, not a blocker, but worth noting.

### Edge / silent failures

- ConvertEmptyStringsToNull middleware is on by default in Laravel 13, so `''` becomes `null` for most fields. Confirm in `bootstrap/app.php`. If it's been removed, empty strings will fail `email`, `numeric`, etc. rules.
- The form always submits `airline`, `flight_number`, `departure_airport`, `arrival_airport`, `property_name`, `room_type` even for non-flight / non-lodging reservations. The validator accepts them as nullable, so this is harmless — but `syncReservationDetails` (TripReservationController:142-198) reads them based on `reservation->type`, so a stray value won't leak. Verify.
- CSRF / session expiry produces a 419 redirect, not a 422. Inertia handles it but a stale tab is a real path to "nothing happens." Worth flashing.

## Strategy

Five phases, sequential.

1. **Audit + reproduce.** Phase 01 — open the form on the user's actual trip, walk every input, capture the network response for at least four induced failures (bad timezone, bad date order, bad email, oversized string). Confirm whether the form is even reachable on the current trip (panel visibility, `can_edit`, `trip.collaborators` membership). Output: a JSON inventory of every silent-failure path in `ai/state/reservation-create.json::audit_findings`.
2. **Wire field-level errors on the add + edit forms.** Phase 02 — `InputError` for every input that has a matching rule. A top-of-form error summary that lists the first three issues. A `scrollToFirstError` helper that focuses the first invalid input on submit failure. A toast on 422 saying "Couldn't save — check the highlighted fields." Same treatment for the inline edit form (the edit form has the same DNA bug).
3. **Replace the freeform timezone Inputs with a real picker.** Phase 03 — small inline `TimezoneSelect` component backed by `\DateTimeZone::listIdentifiers()` (shipped once via shared Inertia prop). Defaults: trip's `destination_timezone` if shipped (per `trip-timezones`), else `home_timezone`, else browser-detected. Synonyms map for common typos (`pst`, `est`, `cst`, `gmt`, `uk`). Removes the single biggest silent-422 generator.
4. **Validator + contract hardening.** Phase 04 — Make `after_or_equal:starts_at` timezone-aware (compare instants, not strings). Verify ConvertEmptyStringsToNull is on. Add a `419` interceptor in the Inertia setup that flashes "Your session expired — refresh and try again." Confirm the controller returns `back()` (it does — line 37) and that the redirect preserves form state via Inertia's automatic error handling (it does — verify). Tighten the validator to use `Rule::in($timezones)` rather than the `timezone` rule when we want exact equality with the picker's option set.
5. **Tests + verification.** Phase 05 — Pest feature tests for every documented failure path: happy create, missing title, bad timezone, bad date order, bad email, oversized string, lodging-type create, flight-type create. Pest 4 browser test that walks the form, induces a bad timezone, asserts the inline error renders. Manual verification matrix on the live app at desktop + mobile widths.

## Phases

1. [Audit And Reproduction](01-audit-and-reproduction.md)
2. [Field-Level Errors And Submit Feedback](02-field-level-errors-and-submit-feedback.md)
3. [Timezone Picker In The Form](03-timezone-picker-in-the-form.md)
4. [Validator And Contract Hardening](04-validator-and-contract-hardening.md)
5. [Tests And Verification](05-tests-and-verification.md)

## Implementation Order

Strictly sequential.

- Phase 01 gates everything — the catalog of broken sites lives in its output.
- Phase 02 is the user-visible unblock. Even if nothing else shipped, a user staring at a 422 would now see *why*.
- Phase 03 removes the single largest 422 generator. Defers gracefully if `trip-timezones` Phase 02 hasn't shipped (just defaults from browser).
- Phase 04 is server- and contract-side polish; no UI changes the user notices unless their session expires.
- Phase 05 is the gate.

## Decisions Baked In (Override In Phase 01 If You Disagree)

| Decision | Rationale |
| --- | --- |
| **Every input that maps to a server rule gets its own `InputError`.** A summary block at the top of the form lists the first three. No more silent fields. | The reported bug is "I can't make another reservation" — the fix is to tell the user what's wrong, not to assume the server is the problem. |
| **A toast / inline banner on every 422 with the message "Couldn't save — check the highlighted fields."** Survives even if individual `InputError` components are missed during the refactor. | A second safety net so the user is never staring at an unresponsive form. |
| **Timezone inputs become a constrained picker on day one of Phase 03.** No freeform fallback. | The freeform path is the dominant silent-422 source. Removing it removes the bug. |
| **The picker's option list is `\DateTimeZone::listIdentifiers()` shipped once via a shared Inertia prop.** Hydrated client-side into a grouped combobox. | Avoids per-request bloat and lets the server be the source of truth for what "valid" means. |
| **`after_or_equal:starts_at` becomes timezone-aware** by replacing the rule with a custom `EndsAtAfterStarts` rule that converts both sides to UTC using their respective `*_timezone` fields before comparing. | A flight from LAX to MNL crosses midnight in one direction and "before/after" depends on which clock you're reading. Wall-clock comparison is wrong for cross-tz reservations. |
| **No flight_details fields on the add form.** They stay on the edit form. | Keeps the add form's vertical real estate sane on mobile and avoids gold-plating during the unblock. Edit-then-fill is the existing pattern. |
| **The `post` helper grows a `scrollToFirstError` + `onError` toast path.** Used by every form on the page that posts to a validated endpoint, not just reservations. | The DNA bug is shared across the page; fixing once benefits packing/cost/task/document/reminder forms too. Bounded scope — the helper is local to `Trips/Show.vue`. |
| **419 (session expired) gets a separate toast** wired into the Inertia router setup, not the form helper. | 419 isn't a per-form problem; it's a global "your tab is stale" problem. |
| **No new database columns.** | The bug is in feedback + validation, not data. |

## Acceptance Criteria

- Audit document (Phase 01) catalogs every silent-failure path the user can hit on create, with the matching server rule and the missing UI surface.
- Every input on the add reservation form has a working `InputError` and the top-of-form summary lists the first three errors after a failed submit.
- The form scrolls and focuses the first invalid input on a failed submit.
- A toast appears on every 422 with copy: "Couldn't save — check the highlighted fields."
- The freeform timezone inputs are replaced by a `TimezoneSelect` picker. Submitting the form with any picker value succeeds against the `timezone` rule.
- A flight from LAX to MNL with `starts_at = 2026-09-26T22:00 / Asia/Manila` and `ends_at = 2026-09-28T06:00 / Asia/Manila` saves cleanly. A second example with cross-tz fields saves cleanly using the new timezone-aware comparison.
- 419 session-expired responses surface a "Your session expired — refresh and try again." toast.
- `php artisan test --compact --filter=Reservation` is green and covers happy create, every failure mode, lodging create, flight create, and a cross-tz `after_or_equal` case.
- A Pest 4 browser test induces a bad timezone, submits, and asserts the field-level error renders.
- All standard gates pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`.
- The same field-level error treatment also lands on the inline edit reservation form (lines 1276-1411). Regression check that edit still works for flight, lodging, and custom types.

## Out Of Scope

- Adding flight_details inputs to the add form (stays on edit, by design).
- Splitting the add reservation form into a multi-step wizard.
- Replacing the add form with the mobile `EditSheet` from the `mobile-ui-ux` module (handled there if/when that ships).
- Re-architecting `syncReservationDetails` or the `flight_segments` / `lodging_stays` per-type sidecar tables.
- An airport-autocomplete picker for the flight fields.
- An import-confirmation-and-prefill flow (already exists; not on this path).
- A draft-saving / autosave feature for half-filled reservations.
- Bulk reservation create.
- Per-trip permissions changes (a user with `can_edit = false` continues to see no add form at all; that's correct).
