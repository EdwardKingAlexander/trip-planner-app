# Phase 05 - Verification And Release

## Goal

Prove the sidecar persists with lazy-create / lazy-delete semantics, the validation rules behave under wrong types and oversized inputs, the form roundtrips cleanly, the read-only summary surfaces correctly, and the release gates pass.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phases 01–04
- Blocker: none

## Verification Matrix

For an authenticated user with edit rights on a trip that has at least one **flight** reservation and at least one **non-flight** reservation:

1. **No row when fully empty:** open the flight reservation's edit form. Expand "Flight details". Leave every field empty (currency may auto-fill — clear it). Save. Confirm `flight_details` table has no row for this reservation.
2. **Lazy create on first value:** open the same form. Set cabin = Economy. Save. Confirm a new `flight_details` row exists with `cabin_class = 'economy'` and every other column null.
3. **Update in place:** open again. Set carry-on size = "55×40×20 cm". Save. Confirm the same row now has both fields populated and there is still only one row.
4. **Lazy delete on full clear:** open again. Clear cabin and carry-on size back to empty. Save. Confirm the row is deleted.
5. **Type switch wipes details:** populate flight_details. Then change the reservation's `type` from `flight` to `lodging`. Save. Confirm the `flight_details` row is gone and `lodging_stays` row was created.
6. **Currency uppercase coercion:** type `usd` in the currency field. Tab away. Confirm the input now shows `USD` before submission.
7. **Currency cascade — user preference:** set `UserTravelPreference.default_currency = 'EUR'` for the current user. Open a flight that has no `flight_details` row. Confirm the currency input pre-fills with `EUR`.
8. **Currency cascade — most recent cost:** with no user preference, ensure the trip has costs in `GBP`. Open a flight that has no `flight_details`. Confirm the currency input pre-fills with `GBP`.
9. **Currency cascade — fallback:** no preference, no costs. Open a flight. Confirm the currency input pre-fills with `USD`.
10. **Validation: bad cabin:** post a `flight_details.cabin_class` of `'wagon'` (via DevTools or tampered request). Server responds 422 with field error.
11. **Validation: bad currency:** post `'usdx'`. Server responds 422.
12. **Validation: negative fee:** post `carry_on_fee: -5`. Server responds 422.
13. **Validation: too many decimals:** post `carry_on_fee: 12.345`. Server responds 422.
14. **Validation: oversized text:** post a 1001-character `notes`. Server responds 422.
15. **Auth: viewer role denied:** as a viewer-role collaborator, the Edit button is hidden on the reservation card. Confirm a direct PATCH from DevTools returns 403.
16. **Auth: non-participant denied:** sign in as a user not on the trip. PATCH attempt returns 403/404.
17. **Read-only summary present:** with cabin + carry-on + checked filled, confirm the reservation card shows the inline summary line ("Economy · Carry-on … · Checked …").
18. **Read-only summary absent:** with only `notes` filled, confirm the summary line is **hidden** but the "Show full details" toggle is visible.
19. **Read-only fully absent:** with no `flight_details` row, confirm the card has no extra UI compared to today.
20. **Expanded panel contents:** click "Show full details". Confirm only sections with at least one filled field render; empty sections are skipped.
21. **Visa/passport visual distinction:** populate visa and passport. Confirm both render in the amber (or theme-accented) callout style — visually distinct from the generic notes block.
22. **Connection & check-in section:** populate `layover_notes`, `online_check_in_opens`, and `boarding_closes`. Confirm the expanded panel renders the new "Connection & check-in" section with the layover paragraph above and the two side-by-side check-in / boarding cards below. With only one of the two check-in fields filled, the lone card spans appropriately and the empty side is omitted.
23. **Connection & check-in absent:** clear all three new fields. Confirm the section disappears entirely (no empty header).
24. **Mobile layout:** narrow the viewport. Summary line wraps cleanly; expanded panel sections stack; baggage rows and the check-in / boarding cards stay readable.
25. **Theme audit:** cycle through all five themes. Confirm form inputs, summary line, expanded panel, visa callout, and the check-in / boarding cards remain legible.
26. **Realtime broadcast:** as a second participant in another browser, edit a flight detail. Confirm the other participant receives a generic `reservation.updated` notification (no new event type) and the trip page refreshes via `live-trip-collaboration`.
27. **JSON export round-trip:** export the trip as JSON. Confirm `reservations[].flight_details` appears with the expected shape including `layover_notes`, `online_check_in_opens`, and `boarding_closes`. Re-import it (manual paste through the import flow) and confirm details survive — or at least confirm the import doesn't crash on the new fields (importer support is out of scope for this module).

## Programmatic Verification

All must pass:

- `php artisan migrate:fresh --seed` then a round-trip: `php artisan migrate:rollback --step=1` followed by `php artisan migrate`. Confirms the new migration is reversible.
- `php artisan test --compact` — full Pest suite including:
  - `FlightDetailsTest` (Phase 02)
  - existing `TripManagementTest`, `TripDocumentUploadTest`, `PackingTogglePackedTest`, `PackingAttributionNotificationTest` — must remain green.
- `npm run lint:check`
- `npm run build` — Wayfinder regen for the wider `trips.reservations.update` form helper.
- `npm run types:check` (after build).
- `vendor/bin/pint --dirty --format agent`

## Theme + Accessibility Audit

- Cycle the page through all five themes from `themes-plan` — confirm:
  - Cabin select, currency input, baggage row inputs, fee suffix label, and visa/passport callouts remain legible.
  - The `<details>` block summary chevron stays visible; expanded state is announced.
- Tab through the edit form:
  - Each field is reachable in DOM order; `<details>` summary toggles with Enter/Space.
  - The cabin `<select>` and currency `<input>` are reachable before the baggage rows.
- Screen-reader sanity check: each `<fieldset>` reads its `<legend>` as the section name; the summary line in the read-only card is read with the inline plane icon described as decorative (`aria-hidden`).

## Manual Verification Script

1. Sign in as the trip owner. Open `/trips/{id}` Reservations tab.
2. Run verification matrix items 1–6 in order on a flight reservation.
3. Sign out. Sign in as a second user with `default_currency = 'EUR'` set in their travel preferences. Run items 7–9.
4. As the owner again, run 10–14 against the form with DevTools to send malformed payloads.
5. Sign in as a viewer-role collaborator. Run 15.
6. Sign in as a non-participant. Run 16.
7. Back as owner, run 17–23.
8. Open two browser tabs as different participants, run 24.
9. Trigger the JSON export (existing button in the trip header) and verify item 25.

## Regression Watch List

- The existing flight reservation create flow must still work end-to-end (the new `flight_details` rules are nullable and the create form does not need to send the array).
- `flight_segments` continues to be created via `syncReservationDetails` for `type = 'flight'` exactly as before.
- `lodging_stays` continues to be created and cleaned up correctly when the type is or becomes `lodging`.
- Trip JSON export round-trips the new payload (verified by item 25).
- `last_edited_by` continues to populate via `TracksAuthor` on the parent reservation.
- The reservation card's Documents (`document-uploads`) attachment section continues to render below the new flight-details panel without layout collisions.
- Print export (`/trips/{id}/print`) continues to work — flight details are not required to render in print for v1, but the route should not 500 on the new payload.
- `live-trip-collaboration` realtime fan-out still receives `reservation.updated` and refreshes the trip view.

## Handoff

- Update `ai/state/flight-details.json` with `status: verified`, fill `verification[]` with exact commands and results.
- `handoff.summary` covers:
  - Where the sidecar lives (`flight_details` table, `App\Models\FlightDetails`).
  - Where the controller sync logic extended (`TripReservationController::syncReservationDetails` + `cleanFlightDetailsPayload`).
  - Where the form section lives (`Trips/Show.vue` reservation edit branch, behind the `<details>` block; `BaggageRow` component).
  - Where the read-only summary lives (`Trips/Show.vue` reservation read-only branch; `BaggageSummaryRow` component; helper functions `flightDetailsSummary`, `cabinLabel`, `formatPrice`, `hasAnyBaggageInfo`).
  - How to extend the pattern for per-segment overrides later (introduce a sister `flight_segment_details` table keyed by `flight_segments.id`; or add a JSON `overrides` column on `flight_segments` if the override surface is small).
  - Where the suggested-currency cascade lives (`TripController::suggestedCurrencyFor`) and how to evolve it (e.g. weight currencies by recency or count).

## Acceptance Criteria

- All programmatic checks pass.
- Manual script completes with no console errors and no regressions on the existing reservation flow, the documents attachments section, the activity feed, or the trip JSON export.
- Lazy create / lazy delete works in both directions: empty → no row, populated → row, cleared → row deleted.
- Currency cascade returns the right answer in all three scenarios.
- Read-only summary respects the "show only when there is something to show" rule.
- `ai/state/flight-details.json` is updated with `status: verified` and the full verification log.
- `ai/modules/README.md` index entry is in place.

## Out Of Scope

- Visual regression / screenshot diffing.
- Performance benchmarking.
- Marketing copy.
- Backporting flight-details surfaces to the print export beyond a "doesn't crash" gate.
- Building a per-segment override module.
- Adding frequent-flyer / meal-preference fields (deferred per scoping decision).
