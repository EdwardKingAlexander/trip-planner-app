# Phase 03 - Mobile Offcanvas Navigation

## Goal

Make the side menu hidden by default on mobile and tablet, then expose it through a collapsible/offcanvas menu that does not create the current scroll trap.

## Planned Changes

### `resources/js/components/ui/sidebar/SidebarProvider.vue`

- Treat mobile and tablet sidebar state separately from desktop cookie state.
- Default mobile menu state to closed.
- Keep desktop collapsed state persistence only for desktop behavior.

### `resources/js/components/ui/sidebar/Sidebar.vue`

- Ensure the desktop sidebar branch is never rendered as a page column on mobile.
- Use the existing mobile `Sheet` branch as the offcanvas menu.
- Keep body/page scrolling locked while the mobile menu is open.
- Avoid nested scroll in the menu for the current nav size by using compact spacing and a pinned account/footer area.

### `resources/js/components/AppSidebarHeader.vue`

- Keep a visible menu trigger on mobile and tablet.
- Make the trigger easy to tap and visually consistent with the updated UI.

### Navigation Links

- Close the offcanvas menu after a user selects a nav item.
- Preserve existing routes and Wayfinder-generated links.
- Keep keyboard and screen reader behavior intact.

## Mobile Behavior

- Sidebar is hidden by default.
- User taps the menu trigger to open navigation.
- User taps outside, presses escape, or selects a link to close navigation.
- Page scroll resumes from the same page position after the menu closes.

## Acceptance Criteria

- At `390px` and `820px`, the sidebar is not visible by default.
- Mobile menu opens and closes reliably.
- The page does not require scrolling one container to the end before another container responds.
- Navigation links still route to the expected pages.

