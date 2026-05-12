# Packing Attribution Master Plan

## Goal

On a shared trip, every packing item should make the answer to three questions immediate and unambiguous: **who added it**, **who is supposed to pack it**, and **whether they packed it yet**. When the assigned packer ticks an item off (or untick it), the user who added that item gets a personal notification — distinct from the generic "marked packed" broadcast that already fans out to everyone else.

## Status

- Status: `verified`
- State file: `ai/state/packing-attribution.json`
- Last updated: 2026-05-12

## Problem

The existing packing experience is silent about authorship and assignment, and the notification model treats everyone as the same audience.

- `packing_items.created_by_user_id` is already populated by the `TracksAuthor` trait (`app/Concerns/TracksAuthor.php:11-38`), but it is never serialized to the frontend (`app/Http/Controllers/TripController.php:229-232` only attaches `last_edited_by`).
- `packing_items.traveler_name` is a free-text string — a user can type "Sam", "Mom", or "kids", but nothing links the value to an actual account, so the system cannot route a notification to the named person.
- When `togglePacked` fires today (`app/Http/Controllers/TripPlanningController.php:106-126`), `TripCollaborationEventService::record()` notifies *every* participant except the actor with the same generic summary ("marked packed: Sunscreen"). The user who originally added the item has no way to tell from their inbox that this update is about something they personally care about.

The result on a shared trip: the adder doesn't know when their item gets packed, the would-be packer doesn't know which items are theirs, and the same notification text reaches both equally.

## Strategy

Three threads, each independently shippable but designed to compound:

1. **First-class assignment**, not a free-text guess. Add a nullable `assigned_to_user_id` foreign key alongside the existing `traveler_name` so we can target a real account when one is chosen and still write "Mom" or "Kids" as a label for non-collaborator packers. Expose `created_by`, `assigned_to`, and `traveler_name` together in the trip serializer so the frontend renders attribution without N+1 follow-ups.
2. **Targeted notification on toggle**, layered on top of the existing collaboration broadcast. The generic `packing.toggled` event still fires for the non-adder, non-actor audience. A second, focused notification ("Sam packed the sunscreen you added") goes to the adder when the adder is neither the actor nor already covered by the generic broadcast. Both notifications are symmetrical for pack and unpack.
3. **UI that pays back the new data.** Each row gets compact "added by · for" attribution (initials/avatars), the progress header breaks into per-assignee segments, and a "Mine to pack" filter lets the assigned user collapse the list to just their share.

State management mirrors the patterns set by `packing-checkbox`: server is the source of truth, optimistic UI lives row-local, and per-trip filter preferences live in `localStorage` keyed by trip id.

## Phases

1. [Data Model And Attribution Contract](01-data-model-and-attribution-contract.md)
2. [Backend Targeted Notification](02-backend-targeted-notification.md)
3. [Frontend Attribution And Assignment UI](03-frontend-attribution-and-assignment-ui.md)
4. [Progress Segments And Mine Filter](04-progress-segments-and-mine-filter.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Phase 01 freezes the migration shape, the serializer fields, and the assignment endpoint contract so the rest of the work is built against a stable agreement. Phase 02 ships the targeted notification path with focused tests (pack and unpack, adder-is-actor short-circuit, generic-broadcast deduplication). Phase 03 wires the new fields into `Trips/Show.vue` — the form gains an assignee picker that defaults to the current user, every row shows attribution, and the existing optimistic toggle is unchanged. Phase 04 adds the per-assignee progress segments and the "Mine to pack" filter that ride on top of the new field. Phase 05 is the gate.

## Acceptance Criteria

- A new packing item records both `created_by_user_id` (existing) and a nullable `assigned_to_user_id` (new). The form defaults the assignee to the current user and offers every trip participant in the dropdown plus an "unassigned" option for free-text traveler names.
- Existing rows continue to render correctly: rows with no `assigned_to_user_id` fall back to displaying `traveler_name` as the "for" label and never crash the UI.
- When the assigned user (or the adder, or any editor) toggles `is_packed`, the **adder** receives a targeted notification ("{Actor} packed the {label} you added" / "{Actor} unpacked the {label} you added") **only when the adder is not the actor**, and the generic `packing.toggled` broadcast no longer reaches the adder twice.
- Every other participant continues to receive the existing generic `packing.toggled` notification — no regression.
- Each packing row in `Trips/Show.vue` shows compact attribution: initials/avatar for the adder and for the assignee (or `traveler_name` text fallback). Hover/long-press reveals full names.
- The Packing panel header shows per-assignee progress segments alongside the existing "X of Y packed" total.
- A "Mine to pack" filter in the panel header (next to "Hide packed") narrows the list to items where `assigned_to_user_id` equals the current user. Persisted per trip in `localStorage`.
- Activity feed events keep working: `packing.toggled` for the broadcast and a new `packing.packed_for_you` / `packing.unpacked_for_you` event type for the targeted notification — both routable via `NotificationDeepLinkResolver` to the same packing row anchor.
- All standard checks pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`, and `php artisan wayfinder:generate --with-form --no-interaction` regenerates cleanly.
- Verification stays dependency-neutral: no Pest browser/Playwright dependency is added for this module; UI behavior is guarded by focused Feature/Inertia payload tests plus TypeScript, lint, and build checks.

## Out Of Scope

- One-click reassignment from the read-only row (deferred — keep reassignment inside the existing edit form for now).
- Per-category progress segments or category-level filters.
- Drag-and-drop reordering of packing items.
- Bulk assign / bulk pack / bulk clear actions.
- Email or push delivery for the new targeted notification (database channel only, matching the rest of the app).
- Migrating existing `traveler_name` strings to `assigned_to_user_id` automatically by name match (too fragile; users can re-assign through the edit form).
- Acknowledgement / "thanks" reactions on the targeted notification.
- Restricting *who* can toggle packed state (still governed by the existing trip update policy — viewers cannot toggle, editors and the owner can).
