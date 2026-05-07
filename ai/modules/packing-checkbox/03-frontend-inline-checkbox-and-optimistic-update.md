# Phase 03 - Frontend Inline Checkbox And Optimistic Update

## Goal

Put a real, single-click checkbox on every packing row, with an optimistic flip and graceful rollback on failure.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 02 (endpoint shipped)
- Blocker: none

## Planned Changes

### `resources/js/pages/Trips/Show.vue` — Packing panel only

#### Row markup (read-only branch, lines ~705-716 today)

- Replace the static "Packed" pill with an inline `<Checkbox>` from `@/components/ui/checkbox` at the start of the row, plus the existing label text.
- New row layout:
  ```
  [✓ checkbox]  3x Sunscreen · Toiletries        Alex   [Edit]
                  ^ strikethrough + muted when packed
                  notes (existing) below
  ```
- Add `:id="\`packing-item-${item.id}\`"` to the row so the `notification-deep-links` module's anchors work cleanly. (Coordinate with that module — it adds the same attribute. First module to land sets it.)
- Add `aria-label` per Phase 01.
- Disable the checkbox when `!trip.can_edit`.

#### Optimistic toggle handler

- New helper, scoped inside the Packing section's setup logic:
  ```ts
  import { togglePacked } from '@/routes/trips/packing-items';
  import { router } from '@inertiajs/vue3';

  const togglingIds = ref(new Set<number>());
  const togglePackedItem = (item: PackingItem) => {
      const previous = item.is_packed;
      item.is_packed = !previous; // optimistic mutation on the prop
      togglingIds.value.add(item.id);
      router.patch(
          togglePacked.url({ trip: trip.value.id, packingItem: item.id }),
          { is_packed: item.is_packed },
          {
              preserveScroll: true,
              preserveState: true,
              onError: () => {
                  item.is_packed = previous; // rollback
                  rowError.value[item.id] = 'Could not save. Try again.';
                  setTimeout(() => delete rowError.value[item.id], 4000);
              },
              onFinish: () => togglingIds.value.delete(item.id),
          },
      );
  };
  ```
- `togglingIds` powers a small spinner / disabled state on the checkbox while the request is in flight.
- `rowError` is a flat `Record<number, string>` so multiple rows can error independently.

#### Edit-form unchanged

- The existing `Edit` button + form still works. `is_packed` checkbox inside the form remains for now (Phase 01 decision).

#### Visual de-emphasis

- When `item.is_packed`, apply `text-muted-foreground line-through` to the label text only — not to the entire row, so quantity, traveler, and notes stay readable.
- The notes block stays at full opacity so packed items don't lose their context.

### `resources/js/types/index.ts`

- Confirm the `PackingItem` shape on the frontend includes `is_packed: boolean` and `last_edited_by` (already does — no change needed if it does).

### Tests

- Pure component-test pass is not required for this phase; the integration is covered by:
  - The Phase 02 backend test (server side).
  - The Phase 05 browser smoke test (UI loop).
- A type check (`npm run types:check`) is the required gate for this phase.

## State Management

- The optimistic flip mutates the prop in place. Inertia replaces the props on the next visit, so any drift from the server is corrected within one round trip.
- `togglingIds` and `rowError` are module-local refs in `Trips/Show.vue` — no global store, no Pinia.
- No persistence of in-flight state — refreshing during a toggle simply shows the last server-confirmed state.

## Acceptance Criteria

- Clicking the checkbox flips it instantly (no spinner-blocking-click).
- On a successful response, the new state persists across reload.
- On a failure, the checkbox flips back within the network round trip and an inline error appears under the row.
- `trip.can_edit === false` users see a disabled checkbox and cannot toggle.
- The "Packed" pill is gone (the checkbox state is the only indicator).
- `npm run lint:check` and `npm run types:check` pass.

## Out Of Scope

- Removing the in-form `is_packed` checkbox (Phase 01 decision: keep for now).
- Progress count and "hide packed" filter (Phase 04).
- Bulk select / bulk toggle.
