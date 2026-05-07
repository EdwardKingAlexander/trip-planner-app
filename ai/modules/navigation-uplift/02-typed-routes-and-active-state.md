# Phase 02 - Typed Routes And Active State

## Goal

Replace every raw-string `href` in the navigation with a Wayfinder-typed route helper, and harden the active-state logic so exactly one item highlights for any given URL.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 01 (IA decision frozen)
- Blocker: none

## Why This Comes Before Page Building

The current bug masks itself two ways: every broken item sends the user to `/trips`, AND `isCurrentUrl('/trips')` returns true for the "Trips" item, so the click feels like nothing happened. Both have to be fixed for any new page added in Phase 03 to feel like it works. We also want typed routes in place so Phase 03 cannot accidentally reintroduce raw-string regressions.

## Planned Changes

### `resources/js/components/AppSidebar.vue`

- Replace the eight-item `mainNavItems` array with the five-item list approved in Phase 01.
- Each item's `href` becomes a Wayfinder typed call:
  - `import { index as tripsIndex, search as tripsSearch } from '@/routes/trips';`
  - `import { index as notificationsIndex } from '@/routes/notifications';`
  - For Calendar and Reminders, import from the new typed exports created when Phase 03 adds those routes; until then, the audit state file records the planned import paths so Phase 03 lines up.
- Keep the existing visual class strings (sidebar styling) — this phase is wiring only.

### `resources/js/components/NavMain.vue`

- No structural change, but tighten the active-state contract:
  - Continue calling `isCurrentUrl(item.href)` for exact matches.
  - For items that own a sub-tree (e.g., `trips.index` should highlight on `/trips`, `/trips/create`, `/trips/{trip}/...` but NOT on `/trips/search`), use `isCurrentOrParentUrl` with an explicit `exclude` list.
  - Add an optional `matchMode: 'exact' | 'prefix'` and `excludePrefixes?: string[]` to `NavItem` so the per-item rule lives next to the item, not inside `NavMain`.

### `resources/js/types/index.ts` (or wherever `NavItem` is declared)

- Extend `NavItem`:
  ```ts
  type NavItem = {
      title: string;
      href: string | RouteUrlObject; // Wayfinder helpers return either
      icon?: Component;
      matchMode?: 'exact' | 'prefix';
      excludePrefixes?: string[];
  };
  ```
- Backwards-compatible: omitted fields keep current behavior.

### `resources/js/composables/useCurrentUrl.ts`

- Add an `excludePrefixes` parameter to `isCurrentOrParentUrl` so a prefix match can be vetoed by a more specific sibling (e.g., Trips matches `/trips/*` but excludes `/trips/search` so Search owns that highlight).
- No change to existing call sites — the new parameter is optional.
- Add a small unit test in `tests/Unit/composables` (Pest) covering: exact match, prefix match, prefix match with one excluded subtree, query-string ignoring, hash ignoring.

### `resources/js/components/AppHeader.vue`

- Same Wayfinder migration for the desktop `mainNavItems` and `Link` to the dashboard logo (`dashboard()` already used — leave it).
- This phase only swaps routing primitives in the header; full header cleanup (removing dead Repository/Documentation links and wiring the search button) is Phase 04.

## Active-State Rules (codified)

| Item | matchMode | excludePrefixes |
| --- | --- | --- |
| Trips | `prefix` (`/trips`) | `['/trips/search']` |
| Search | `exact` | — |
| Calendar | `exact` | — |
| Reminders | `exact` | — |
| Notifications | `exact` | — |

Recorded in state JSON so Phase 03 can validate against them.

## State Management

- The runtime active state owner is `useCurrentUrl` (already module-scoped via `usePage()`); no new global state is added.
- The active-state rule table is duplicated in code (the `NavItem` array) and in the state JSON. The JSON is the contract; code must match it. A drift test in Phase 05 asserts they agree.

## Acceptance Criteria

- Grep `resources/js/components/AppSidebar.vue` and `resources/js/components/AppHeader.vue` for the literal string `'/trips'`. Zero hits — every URL is produced by a Wayfinder helper.
- On `/trips`, only the Trips item is highlighted.
- On `/trips/search`, only the Search item is highlighted.
- On `/trips/{id}`, only the Trips item is highlighted (prefix match, search is excluded).
- On `/notifications`, only the Notifications item is highlighted.
- `npm run types:check` passes (the `NavItem` shape change is the most likely regression vector).
- New unit test for `useCurrentUrl` passes.

## Out Of Scope

- Building Calendar / Reminders pages (Phase 03).
- Removing the dead header links and wiring the broken header search button (Phase 04).
- Any visual restyle of the active indicator.
