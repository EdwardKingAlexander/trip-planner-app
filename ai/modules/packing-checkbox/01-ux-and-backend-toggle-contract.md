# Phase 01 - UX And Backend Toggle Contract

## Goal

Lock the user-facing behavior and the backend endpoint shape so Phases 02–04 build against a frozen agreement.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: none
- Blocker: none

## Audit Inputs

- `app/Models/PackingItem.php` — `is_packed` already exists, cast to boolean.
- `app/Http/Controllers/TripPlanningController.php:69-85` — current `updatePacking` action accepts the full validated payload (label, traveler_name, category, quantity, is_packed, sort_order, notes) and emits a `packing.updated` event.
- `resources/js/pages/Trips/Show.vue:683-734` — current Packing panel.
- `resources/js/routes/trips/packing-items.ts` — Wayfinder helpers `store` and `update`.

## UX Rules (frozen)

1. **Inline checkbox** lives on the left edge of every packing row in read-only view. Tapping it toggles packed state.
2. **Optimistic update** — the box flips instantly. If the server rejects (auth, validation, network), the box flips back and a small inline error appears under the row for ~4 seconds.
3. **Disabled when not editable** — if `trip.can_edit` is false, the checkbox renders as a read-only indicator (visually identical but `disabled`, no click handler).
4. **Edit form parity** — the existing in-form `is_packed` checkbox stays for now; users get the same answer either way. Phase 03 may consider removing the in-form checkbox in a follow-up if the inline version covers every case.
5. **Visual de-emphasis** for packed items: row text gets `text-muted-foreground` and the label gets a strikethrough. The "Packed" pill is removed (the checkbox already conveys the same state — keeping the pill would be redundant).
6. **Progress header** above the list: "{packed} of {total} packed" with a thin progress bar using the active theme's `--primary` token.
7. **Hide-packed toggle** in the panel header: a small `Switch` component labeled "Hide packed". Off by default. State persists per-trip in localStorage so the user's filter choice survives reloads.
8. **Keyboard:** the checkbox is a real `<input type="checkbox">` with a visible focus ring. `Space` toggles. Tab order follows DOM order top-to-bottom.
9. **Accessibility:** each checkbox has `aria-label="Mark <label> as packed"` (or "as unpacked" depending on state). Screen readers announce the toggle outcome.

## Backend Contract (frozen)

A focused toggle endpoint, separate from the full update:

```
PATCH /trips/{trip}/packing-items/{packingItem}/packed
Body: { is_packed: boolean }
Response: 302 back() with success flash, OR 422 with validation errors
Authorization: trip update policy
Activity event: packing.toggled, summary "marked packed: {label}" or "marked unpacked: {label}"
```

Why a focused endpoint instead of reusing `update`:

- Smaller payload — only one field validated.
- Distinct activity event type (`packing.toggled` vs `packing.updated`) so the activity feed reads naturally and notifications can be styled differently.
- Easier to authorize and rate-limit in isolation if the frontend ever spams toggles.

## Open Questions To Resolve Before Phase 02

- Confirm "remove the Packed pill" rather than keeping it next to the checkbox.
- Confirm "Hide packed" persistence is per-trip in localStorage rather than a user-wide preference.
- Confirm the in-form `is_packed` checkbox stays in the edit form (vs. removing it).

Record answers in `ai/state/packing-checkbox.json` `decisions[]` before Phase 02.

## State Management

- Server is the source of truth for `is_packed`.
- Optimistic local state lives in the row component for the duration of the in-flight request — no global store.
- "Hide packed" filter state lives in component-local ref + localStorage keyed `packing-hide-packed:{tripId}`.
- Progress count is a derived computed over `trip.packing_items` — no separate state.

## Acceptance Criteria

- UX rules and endpoint shape are written into the state JSON.
- Open questions answered.
- Endpoint URL, body shape, response codes, and event type are documented and unchanged in later phases.

## Out Of Scope

- Building the endpoint (Phase 02).
- Wiring the checkbox (Phase 03).
- Progress + filter UI (Phase 04).
