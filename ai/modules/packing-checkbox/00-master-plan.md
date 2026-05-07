# Packing Checkbox Master Plan

## Goal

Let users tick a packing item as packed directly from the Packing list, without entering edit mode. The data already supports it; the UI doesn't expose it.

## Status

- Status: `planned`
- State file: `ai/state/packing-checkbox.json`
- Last updated: 2026-05-06

## Problem

`PackingItem` already has an `is_packed` boolean column with a cast (`app/Models/PackingItem.php:13-18`). The trip show Packing panel currently only:

- Shows a static "Packed" pill (`resources/js/pages/Trips/Show.vue:710`) when an item is packed.
- Hides any toggle inside the edit form's checkbox at line 696. To mark something packed, the user must:
  1. Click "Edit" on the row.
  2. Tick the `is_packed` checkbox inside the form.
  3. Click "Save".
  4. Wait for the page to refresh.

That's three clicks, a form submission, and a page round trip per item. For a packing list of 30+ items this is unusable. There's also no progress indicator anywhere — the user has no sense of how close they are to "fully packed."

## Strategy

Two changes that compound:

1. **Inline checkbox** on every packing row in the read-only view. Click toggles `is_packed` via a focused backend endpoint, with optimistic UI and rollback on failure.
2. **Progress polish** — a "X of Y packed" header, an optional "hide packed" filter, and packed items visually de-emphasized so the unpacked items stand out.

Backend keeps using the existing activity event system so collaborators see `packing.toggled` events in real time (already plumbed through `live-trip-collaboration`).

## Phases

1. [UX And Backend Toggle Contract](01-ux-and-backend-toggle-contract.md)
2. [Backend Toggle Endpoint](02-backend-toggle-endpoint.md)
3. [Frontend Inline Checkbox And Optimistic Update](03-frontend-inline-checkbox-and-optimistic-update.md)
4. [Progress Indicator And Polish](04-progress-indicator-and-polish.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Phase 01 freezes the endpoint shape and UX rules. Phase 02 ships the endpoint plus tests so the frontend has a stable target. Phase 03 wires the checkbox. Phase 04 layers on the progress and filtering UX. Phase 05 gates the release.

## Acceptance Criteria

- A user can mark a packing item as packed (or un-packed) with a single click on a checkbox in the row, with no full page reload.
- The checkbox responds optimistically — visual change is instant; backend persists in the background; failure rolls back with a brief error toast.
- Editing other fields on a packing item still works through the existing edit form — no regression.
- The Packing panel header shows a live progress count ("12 of 30 packed").
- A toggle to hide already-packed items keeps the list focused on what's left.
- Packed items are visually de-emphasized (muted text, strikethrough on the label) so the unpacked items stand out.
- Activity events still fire for collaborators (`packing.toggled` event type, summary "marked packed: <label>" or "marked unpacked: <label>").
- All standard checks pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`.

## Out Of Scope

- Bulk pack / bulk clear (could come later as a follow-up).
- Drag-and-drop reordering (not requested).
- Packing templates or import/export.
- Per-traveler progress bars (the data supports it, but adds visual complexity — defer until requested).
- Notifications when a collaborator packs an item (the existing realtime event already covers this; no new notification type).
