# Phase 05 - Verification And Release

## Goal

Prove the toggle works, the optimistic UI rolls back on failure, the progress + filter behave correctly, and gate the release behind the standard suite.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 01–04
- Blocker: none

## Verification Matrix

For an authenticated user with at least 10 packing items mixed across packed/unpacked:

1. **Single toggle:** click an unpacked item's checkbox. Box flips instantly, progress count increments, item drifts to the bottom of the list.
2. **Toggle back:** click the same item again. Box flips, count decrements, item returns to its sort order.
3. **Optimistic rollback:** with the network throttled to fail (use Chrome DevTools "Offline" mode), click an item. Box flips momentarily, then flips back, inline error appears.
4. **Hide packed:** with several packed items, toggle "Hide packed" — packed rows disappear, count header still shows "{packed} of {total} packed". Reload — filter persists.
5. **Empty state:** filter on, all items packed, panel shows the "Everything's packed" state.
6. **Read-only collaborator:** as a viewer (not editor), checkboxes render but are disabled and don't accept clicks.
7. **Edit form parity:** open the row's edit form, change `is_packed` there, save. The new state shows in the inline checkbox.
8. **Activity feed:** check the trip's activity feed (or `live-trip-collaboration` realtime stream) — `packing.toggled` events show with the right summary string.
9. **Cross-trip filter independence:** trip A has "Hide packed" on, trip B has it off. Switching between them respects each trip's setting.

## Programmatic Verification

- `php artisan test --compact` — full Pest suite, including the new `PackingTogglePackedTest`.
- `npm run lint:check`
- `npm run build` — Wayfinder regen for the new endpoint helper.
- `npm run types:check` (after build).
- `vendor/bin/pint --dirty --format agent`

All five must pass.

## Theme + Accessibility Audit

- Pulse the page through all five themes from `themes-plan` (when shipped) — confirm checkbox, progress bar, and disabled state remain legible at WCAG AA contrast.
- Tab through the packing list with a keyboard. Confirm Space toggles each checkbox and focus ring is visible on every theme.
- Run a screen-reader sanity check on at least one row: confirm the `aria-label` updates when state flips.

## Manual Verification Script

1. As trip owner, open `/trips/{id}` Packing tab.
2. Run through the verification matrix above.
3. Have a collaborator open the same trip in another browser.
4. Toggle three items — confirm the collaborator sees them flip in near real time (covered by `live-trip-collaboration` realtime).
5. Mark all items packed — confirm the celebratory empty state appears with "Hide packed" on.

## Regression Watch List

- Existing edit-form save flow must still work end-to-end (the in-form `is_packed` checkbox stays per Phase 01).
- `last_edited_by` must populate correctly via `TracksAuthor` on toggle (covered by Phase 02 test).
- `notification-deep-links` module relies on `id="packing-item-{id}"` on each row — confirm the attribute is still present after this module's row markup changes.
- Trip print export (`TripExportService`) must still render packing items correctly — confirm `is_packed` continues to surface in print output unchanged.

## Handoff

- Update `ai/state/packing-checkbox.json` with `status: verified`, fill `verification[]` with exact commands and results.
- `handoff.summary` covers: where the toggle endpoint lives, where the optimistic logic lives, how to extend to other "single-field toggle" features (Tasks `is_complete`, Reminders `done_at` follow the same pattern).

## Acceptance Criteria

- All programmatic checks pass.
- Manual script completes with no console errors and no regressions on the edit form, the activity feed, or `last_edited_by`.
- Future agents can repeat this pattern for `TripTask.is_complete` by copying the endpoint shape and the inline-checkbox component.

## Out Of Scope

- Visual regression / screenshot diffing.
- Performance benchmarking beyond the basic "feels instant" check.
- Marketing copy.
