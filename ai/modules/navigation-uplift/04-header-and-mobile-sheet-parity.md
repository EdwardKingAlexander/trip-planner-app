# Phase 04 - Header And Mobile Sheet Parity

## Goal

Make the header carry its own weight (no dead controls, no starter-kit leftovers) and make the mobile menu show the exact same item set as the desktop sidebar so users never see a different navigation depending on their viewport.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 02 (typed routes), Phase 03 (final nav list)
- Blocker: none

## Planned Changes

### `resources/js/components/AppHeader.vue`

#### Remove starter-kit cruft

- Delete the `rightNavItems` array (Repository / Documentation external links). They link to public Laravel docs and have no place in an authenticated personal vacation app.
- Delete the corresponding `<TooltipProvider>` block in the right-side cluster that renders those links, and the matching mobile section that lists them under the Sheet. Keep the `Sheet` shell — it will be repurposed.

#### Wire or remove the Search icon

- The `<Button>` containing the `Search` icon has no `@click` and no `as-child` link wrapper. Two options:
  - **Wire it (preferred):** wrap with `<Link :href="tripsSearch()">` using `as-child`, give it an `aria-label="Search trips"`, and add a `Tooltip` matching the avatar styling.
  - **Remove it:** if the sidebar Search item is judged sufficient, delete the icon button entirely.
- Default to wiring it. The user's mental model of a header search icon is well established and the existing `/trips/search` page absorbs the click cheaply.

#### Header `mainNavItems`

- Replace with the same five-item list the sidebar uses (sourced from a shared module).

### `resources/js/lib/navigation.ts` (new)

- Single source of truth for the global nav list:
  ```ts
  export const globalNavItems: NavItem[] = [...];
  ```
- Both `AppSidebar.vue` and `AppHeader.vue` import from here. Removes the duplication that caused the sheet/sidebar drift in the first place.

### Mobile Sheet inside `AppHeader.vue`

- Render `globalNavItems` (not just `Dashboard`) inside the `SheetContent`.
- Active-state behavior matches the desktop rules from Phase 02.
- Each `Link` calls `setOpen(false)` on click so the sheet dismisses after navigation — the existing sidebar already does this via `closeMobileNavigation()`; mirror the pattern.
- Sheet still includes the avatar / user menu trigger at the bottom for parity with the sidebar footer.

### Layout question — single mobile menu, not two

The app currently has both a sidebar (with its own mobile sheet behavior, fixed in the `scroll-issue` module) and a header (with its own Sheet menu). On mobile, only one should be visible.

- If the authenticated layout uses `AppSidebarLayout` (it does, per `scroll-issue/02-desktop-single-scroll-shell.md`), the header sheet is redundant on mobile and should be removed entirely — the sidebar's offcanvas already handles mobile navigation.
- Decision recorded in the state file: **drop the header's mobile Sheet block; keep the desktop nav slot in the header for breadcrumbs and the avatar.**

## Active-State In The Mobile Sheet

Same rules as Phase 02 — the rules table is shared, so the sheet inherits correct behavior for free as long as it consumes `globalNavItems` and `useCurrentUrl`.

## State Management

- No new state. The header consumes `usePage()` for the auth user and `useCurrentUrl` for highlighting, identical to the sidebar.
- `globalNavItems` is a static module-level constant; if Phase 03 ever needs runtime gating (e.g., hide Notifications when none configured), introduce that as a composable then, not now.

## Acceptance Criteria

- `AppHeader.vue` has no Repository / Documentation external links and no orphan icon buttons.
- The header's Search icon either navigates to `trips.search` (preferred) or is removed.
- `AppSidebar.vue` and `AppHeader.vue` both import their item list from `resources/js/lib/navigation.ts`.
- On mobile, exactly one navigation surface is reachable — no duplicate sheet menus.
- Smoke test on mobile width: tap each nav item and confirm the sheet closes and the page navigates.
- `npm run lint:check` and `npm run types:check` pass.

## Out Of Scope

- Restyling the header beyond the cleanup above.
- Adding new header controls (theme picker, breadcrumbs redesign).
- Replacing the `lucide-vue-next` icons with a different set.
