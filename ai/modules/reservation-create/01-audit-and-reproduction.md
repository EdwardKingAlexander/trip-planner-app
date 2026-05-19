# Phase 01 - Audit And Reproduction

## Goal

Reproduce "I cannot make another reservation" deterministically and catalog every silent-failure path on the Add Reservation form. No code changes. Output: a JSON inventory under `audit_findings` in `ai/state/reservation-create.json` plus a one-paragraph repro note naming the exact trip, the exact submitted values, and the exact server response that produced the silent failure.

Without this, Phase 02 risks fixing the loud symptom (no error UI) while missing edge cases that would still fail invisibly after the refactor.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: none
- Blocker: none

## Audit Inputs

- The live app at `http://vacation-plan-app.test/trips/{id}` for the user's existing trip — preferably the same Manila trip used in the `trip-timezones` audit so we can stack findings.
- Browser DevTools Network tab with **Preserve log** + **Disable cache** on.
- The Reservations panel of `Trips/Show.vue` (lines 1271-1626).
- Relevant code paths:
  - `app/Http/Controllers/TripReservationController.php` — `store`, `validatedReservation`, `syncReservationDetails`.
  - `app/Models/Reservation.php` — fillable, casts.
  - `resources/js/pages/Trips/Show.vue` — `reservationForm` (233-254), the form template (1588-1626), the `post()` helper (551-556).
  - `app/Policies/TripPolicy.php` — `update` rule (gates the form's reachability via `trip.can_edit`).
  - `bootstrap/app.php` — to confirm `ConvertEmptyStringsToNull` middleware is enabled.

## Audit Method

Two passes.

### Pass A: code inventory

For every field in `validatedReservation`, record one row:

| Field | Rule | Form input present? | InputError present? | Failure mode |
| --- | --- | --- | --- | --- |
| `type` | required, string, max:40 | yes (1589-1596 `<select>`) | no | hard to trigger; defaults to flight |
| `title` | required, string, max:160 | yes (1597) | yes (1598) | covered |
| `provider_name` | nullable, string, max:160 | yes (1599) | no | silent on >160 chars |
| `booking_reference` | nullable, string, max:120 | yes (1600) | no | silent on >120 chars |
| `status` | required, in:... | no (defaults to 'reserved') | no | shouldn't fail |
| `starts_at` | nullable, date | yes (1602, datetime-local) | no | silent on malformed |
| `starts_timezone` | required, timezone | yes (1606, freeform Input) | no | **silent on typo — main suspect** |
| `ends_at` | nullable, date, after_or_equal:starts_at | yes (1603, datetime-local) | no | silent on reverse order |
| `ends_timezone` | required, timezone | yes (1607, freeform Input) | no | **silent on typo — main suspect** |
| `location_name` | nullable, string, max:160 | yes (1623? or 1599) | no | silent on >160 |
| `address` | nullable, string, max:240 | yes (1623) | no | silent on >240 |
| `contact_phone` | nullable, string, max:80 | no input on add form | n/a | not reachable from add form |
| `contact_email` | nullable, email, max:160 | no input on add form | n/a | not reachable from add form |
| `notes` | nullable, string, max:2000 | yes (1624) | no | silent on >2000 |
| `airline` | nullable, string, max:120 | yes (1611, flight only) | no | silent on >120 |
| `flight_number` | nullable, string, max:40 | yes (1612, flight only) | no | silent on >40 |
| `departure_airport` | nullable, string, max:20 | yes (1615) | no | silent on >20 |
| `arrival_airport` | nullable, string, max:20 | yes (1616) | no | silent on >20 |
| `property_name` | nullable, string, max:160 | yes (1620, lodging only) | no | silent on >160 |
| `room_type` | nullable, string, max:120 | yes (1621) | no | silent on >120 |
| `flight_details` (+ children) | nullable, array, … | not on add form | n/a | n/a |

Each row becomes a finding in `audit_findings`. Findings without an `InputError` are tagged `severity = critical` if `required`, `high` if `nullable` with constraints, `low` if `nullable` open string.

### Pass B: live reproduction

Walk the form at the live URL. For each scenario, record: submitted values → HTTP status → response body keys → what the UI showed.

1. **Happy flight create.** Type a title, leave defaults. Submit. Expect 302 redirect with success flash. If it fails, that's the user's bug exactly — capture the response.
2. **Mistyped timezone.** Type "PST" in `starts_timezone`. Submit. Expect 422 with `errors.starts_timezone` populated and **nothing visible**. Confirm the silent-failure hypothesis.
3. **Reverse date order.** `starts_at = 2026-09-27T14:00`, `ends_at = 2026-09-27T12:00`. Submit. Expect 422 with `errors.ends_at` and nothing visible.
4. **Cross-tz reverse-looking dates.** `starts_at = 2026-09-26T22:00 / Asia/Manila`, `ends_at = 2026-09-26T18:00 / America/Los_Angeles`. The PHT instant is *earlier* than the PDT instant. Confirm whether `after_or_equal:starts_at` compares wall-clock (and passes here despite being logically wrong) or compares timestamps. Document the answer.
5. **Oversized booking_reference.** Paste 200 characters. Submit. Expect 422 silent.
6. **Long valid title only.** Empty everything else, single-char title. Submit. Expect 302 success or 422 — find out which.
7. **Browser-detected timezone.** Open DevTools console: `Intl.DateTimeFormat().resolvedOptions().timeZone`. Note the exact string. Confirm it appears as the default in the form. Confirm it round-trips through PHP's `timezone` rule.
8. **Session expired.** In a second tab, sign out. In the first tab, submit. Expect 419 — does it surface anything?
9. **Network failure.** DevTools → throttle → offline. Submit. Expect Inertia networkError. Does anything surface?
10. **Authorization gap.** Open the form as a viewer-only collaborator (if available). Confirm the form is gated by `trip.can_edit` and not reachable — or confirm it *is* reachable and the POST 403s silently.

### Pass C: form reachability

Confirm:

- The reservations panel renders for the current trip (`activePanel === 'reservations'`).
- The add-reservation `<form>` block at lines 1588-1626 is inside a `<Card>` or aside that is not gated by `v-if="trip.can_edit"` in a way that hides the form when the user is the trip owner.
- The "Add reservation" button (line 1625) is visible above the fold or reachable by scroll.
- No JS console errors at the time the user tries to submit (a stray error in another component can break event handlers).

If any of these fails, log a finding with `category = reachability` and `severity = critical` — the user can't even submit the form, which is a fundamentally different bug than "the form fails silently."

## Output Shape (frozen)

Append to `ai/state/reservation-create.json`:

```json
"audit_findings": [
    {
        "id": "rsv-001",
        "category": "feedback",
        "field": "starts_timezone",
        "rule": "required|timezone",
        "form_input_line": 1606,
        "input_error_present": false,
        "severity": "critical",
        "summary": "Freeform <Input> for starts_timezone with no InputError. Typing 'PST' produces a silent 422.",
        "repro": "Submitted starts_timezone='PST' → 422 {errors:{starts_timezone:['The starts timezone field must be a valid timezone.']}}; UI showed nothing.",
        "proposed_fix_phase": "02 + 03",
        "evidence": "ai/audit/reservation-create-2026-05-18/silent-422-timezone.png"
    },
    {
        "id": "rsv-002",
        "category": "validation",
        "field": "ends_at",
        "rule": "nullable|date|after_or_equal:starts_at",
        "form_input_line": 1603,
        "input_error_present": false,
        "severity": "high",
        "summary": "Reverse-order datetimes silently 422. after_or_equal compares wall-clock strings, ignoring per-leg timezones.",
        "repro": "starts_at=2026-09-27T14:00, ends_at=2026-09-27T12:00 → 422; UI showed nothing.",
        "proposed_fix_phase": "02 + 04",
        "evidence": "ai/audit/reservation-create-2026-05-18/silent-422-date-order.png"
    }
]
```

Plus a top-level `repro` field on the state file:

```json
"repro": {
    "trip_id": 123,
    "trigger": "Open /trips/123, click Add Reservation in the Reservations panel, type 'Test' for title, type 'PST' into the starts timezone field, click Add reservation.",
    "observed": "Nothing happens. Network tab shows POST /trips/123/reservations → 422 with errors.starts_timezone populated. Form remains filled. No toast. No inline message.",
    "expected": "Field error rendered under the timezone input, summary at the top of the form, form scrolled to first error."
}
```

Screenshots under `ai/audit/reservation-create-2026-05-18/`.

## Severity Triage Rules

- `critical` — the user cannot complete the create at all, OR the failure is silent on a required field that the user has no way to reason about (timezone rule).
- `high` — silent failure on a constrained-string field the user can plausibly trip (oversize, bad email, reverse date order).
- `medium` — silent failure on an unlikely-to-trip field (oversize on a textarea max:2000).
- `low` — cosmetic (placeholder copy, layout density).

Phase 02 fixes every `critical` and `high`. Phase 03 removes the dominant `critical` (timezone). Phase 04 catches `419` / `5xx` paths.

## Acceptance Criteria

- `audit_findings` contains one row per field in `validatedReservation`. At least three findings have a confirmed live repro with a captured 422 response body.
- The `repro` field on the state file is filled in with the user's actual trip id and the exact reproduction steps for the dominant failure path.
- Screenshots exist for all critical findings under `ai/audit/reservation-create-2026-05-18/`.
- A definitive yes/no answer to: "Does the add reservation form render and is the submit button reachable for the current user on the current trip?"
- A confirmed answer to: "Does `Intl.DateTimeFormat().resolvedOptions().timeZone` produce a value PHP's `timezone` rule accepts in this browser?"
- Whether ConvertEmptyStringsToNull is enabled in `bootstrap/app.php` is confirmed in writing.

## Out Of Scope

- Fixing anything (Phases 02–05).
- Refactoring `validatedReservation` (Phase 04).
- Adding a timezone picker (Phase 03).
- Writing tests (Phase 05).
