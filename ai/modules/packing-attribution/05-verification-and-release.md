# Phase 05 - Verification And Release

## Goal

Prove the targeted notification routes correctly to the adder, the broadcast no longer double-notifies, attribution renders on every row, and the new progress + filter behave correctly. Gate the release behind the standard suite.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phases 01–04
- Blocker: none

## Verification Matrix

For a shared trip with two linked accounts (call them **Alex**, the trip owner, and **Sam**, an editor collaborator), plus at least one packing item assigned to Alex, one to Sam, one with a free-text traveler ("Mom"), and one unassigned:

1. **Adder gets a personal notification:** sign in as Sam. Toggle Alex's "Sunscreen" item. Sign in as Alex — see exactly one new notification reading "Sam packed the Sunscreen you added", `event_type` = `packing.packed_for_you`. Click it — lands on the trip Packing panel with the row highlighted (covered by `notification-deep-links`).
2. **No double notification:** during the same toggle, Alex's inbox shows exactly one new notification — the targeted one. There is no extra "marked packed: Sunscreen" entry from the generic broadcast.
3. **Other participants still get the generic broadcast:** in the same scenario, add a third participant **Pat** to the trip. After Sam's toggle, Pat sees a single "Sam marked packed: Sunscreen" notification with `event_type` = `packing.toggled`. Alex does not see this duplicate.
4. **Symmetric on unpack:** Sam un-checks the same item. Alex sees "Sam unpacked the Sunscreen you added" (`packing.unpacked_for_you`). Pat sees "Sam marked unpacked: Sunscreen" (`packing.toggled`).
5. **Adder packs own item — no targeted notification:** Alex toggles an item Alex added. Alex's inbox unchanged. Sam and Pat each receive the generic broadcast.
6. **Legacy row (no adder) still broadcasts:** find or fabricate a row with `created_by_user_id = null`. Toggle it. Generic broadcast goes out to all non-actors. No targeted notification attempted; no error in `storage/logs/laravel.log`.
7. **Assignment defaults to current user:** open the Packing panel, click "Add item" — the assignee dropdown is pre-set to the current user, with `(me)` suffix.
8. **Free-text traveler still works:** create an item with assignee = "Unassigned" and traveler_name = "Mom". Row renders "for Mom". Toggling that item still triggers the targeted notification to the adder if the adder is not the actor — and the row is grouped under a "Mom" bucket in the segmented progress bar.
9. **Mine to pack filter:** sign in as Sam. Toggle "Mine to pack". List collapses to only items where `assigned_to_user_id = Sam.id`. The segmented progress bar still shows all buckets (filter narrows the list, not the progress).
10. **Mine + Hide packed:** with "Mine to pack" on, toggle "Hide packed". List shows only unpacked items assigned to Sam. Reload — both filters persist (per-trip `localStorage` keys).
11. **Cross-trip filter independence:** trip A has "Mine to pack" on; trip B has it off. Switching between trips respects each trip's setting.
12. **Per-assignee progress segments:** the bar shows one segment per assignee, sized proportional to that assignee's share of the total. Filling within a segment reflects packed-ratio for that assignee.
13. **Read-only viewer:** as a viewer-role collaborator, the assignee picker is disabled in the form, the inline checkbox is disabled, and the row attribution chips render correctly.
14. **Edit form parity:** open the edit form for a row, change the assignee and `is_packed` together, save. Both changes persist; `packing.updated` fires (no `packing.toggled` from the form save — the edit form goes through `updatePacking`, not `togglePacked`).
15. **Activity feed:** open the trip activity feed (or `live-trip-collaboration` realtime stream). The new `packing.packed_for_you` and `packing.unpacked_for_you` events appear with the personalized summary.
16. **Deep link still works:** clicking the targeted notification from the inbox opens the trip Packing panel with the row scrolled into view and pulsed (covered by `notification-deep-links`).

## Programmatic Verification

All five must pass:

- `php artisan migrate:fresh --seed` then `php artisan migrate:status` — confirms the new migration is registered and reversible (run `php artisan migrate:rollback --step=1` and `php artisan migrate` to round-trip).
- `php artisan test --compact` — full Pest suite, including:
  - the new `PackingAttributionNotificationTest` (Phase 02),
  - the new `PackingAttributionPayloadTest` (Phase 01/03/04),
  - the existing `PackingTogglePackedTest` and any other packing/trip-management feature tests (must remain green).
- `npm run lint:check`
- `npm run build` — Wayfinder regen if `validatedPacking` was widened (`assigned_to_user_id`).
- `npm run types:check` (after build).
- `vendor/bin/pint --dirty --format agent`

## Theme + Accessibility Audit

- Cycle the page through all five themes from `themes-plan` — confirm:
  - Avatar chips remain legible (initial text contrast against `bg-primary/15`).
  - Segmented progress bar segments stay visible against the `bg-muted` track.
  - The "this is mine" highlight ring is visible without overpowering the row.
- Tab through the form: assignee `<select>` is reachable, has a focus ring, and is reachable before the submit button.
- Screen-reader sanity check on at least one row: the `aria-label` on each avatar chip reads the full name; the `for` chip reads "for {name}" or "for {traveler_name}" or "for anyone" as appropriate.

## Manual Verification Script

1. Sign in as Alex (trip owner). Open `/trips/{id}` Packing tab.
2. Add three items defaulting to self, then change one to be assigned to Sam, one to "Mom" (free-text traveler), and leave one unassigned.
3. Confirm row attribution renders correctly.
4. Sign out, sign in as Sam (collaborator). Confirm the form's assignee picker defaults to Sam (current user).
5. Toggle Alex's item — sign back in as Alex, confirm the targeted notification.
6. Run through the verification matrix items 1–16.

## Regression Watch List

- `packing-checkbox` optimistic toggle still works (the Phase 02 controller change to `togglePacked` must preserve the same response shape — `back()` redirect with success flash).
- `last_edited_by` continues to populate via `TracksAuthor` on every update (covered by Phase 02 test).
- `notification-deep-links` resolver still routes to `#packing-item-{id}` for both the generic and the targeted event types (no resolver change in this module — confirm with one resolver unit test using the new event type).
- The activity feed renders the new event types without crashing (it reads `summary` from the event row — should be transparent).
- Existing rows with no `created_by_user_id` (legacy / seed data) still load on the trip detail page.
- Existing rows with no `assigned_to_user_id` continue to show `traveler_name` as the "for" label.
- `live-trip-collaboration` realtime fan-out still receives `packing.toggled` for non-adder participants and continues to refresh their trip view.

## Handoff

- Update `ai/state/packing-attribution.json` with `status: verified`, fill `verification[]` with exact commands and results.
- `handoff.summary` covers:
  - Where the targeted notification is dispatched (`TripCollaborationEventService::notifyAdder()`, called from `TripPlanningController::togglePacked()`).
  - How to extend the targeted-notification pattern to `TripTask` completion or `TripReminder` toggles (same shape: exclude the adder from the broadcast, send a personalized `*.for_you` event to the adder).
  - Where the assignee picker, row attribution, and progress segments live in `Trips/Show.vue`.

## Acceptance Criteria

- All programmatic checks pass.
- Manual script completes with no console errors and no regressions on the edit form, the activity feed, the inline toggle, or `last_edited_by`.
- The targeted notification reaches the adder exactly once on pack and once on unpack, never on adder-is-actor.
- Row attribution and per-assignee progress segments render correctly across all themes.
- The `ai/state/packing-attribution.json` file is updated with `status: verified` and the full verification log.
- `ai/modules/STATE.md` is updated with the module's verification entry.

## Out Of Scope

- Visual regression / screenshot diffing.
- Performance benchmarking on packing lists with > 200 items.
- Marketing copy / changelog entries.
- Backporting attribution to other entity types (tasks, reminders) — separate module if desired.
