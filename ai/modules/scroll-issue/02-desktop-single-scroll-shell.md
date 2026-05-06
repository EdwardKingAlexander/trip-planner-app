# Phase 02 - Desktop Single-Scroll Shell

## Goal

Refactor the authenticated desktop shell so the sidebar and main content do not compete for vertical scrolling.

## Planned Changes

### `resources/js/components/ui/sidebar/Sidebar.vue`

- Replace the fixed desktop sidebar pattern with a normal-flow or sticky sidebar inside the app shell.
- Remove desktop `h-svh` plus `overflow` behavior that turns the sidebar into an independent scroll container.
- Keep the existing collapsed icon state and width variables so the visual behavior does not regress.
- Preserve the `Sheet` branch for mobile, but keep desktop and mobile layout responsibilities separate.

### `resources/js/components/ui/sidebar/SidebarContent.vue`

- Remove default `overflow-auto` from persistent desktop navigation.
- Keep content in a flexible column that fits the shell without creating an inner vertical scrollbar.
- If overflow is unavoidable for very small heights, prefer the document/page scroll over a sidebar-only scroll.

### `resources/js/components/ui/sidebar/SidebarInset.vue`

- Ensure the main content column uses `min-w-0` and does not become an independent scroll region.
- Keep the inset visual styling but remove any class combination that causes the main region to scroll separately from the document.

### `resources/js/layouts/app/AppSidebarLayout.vue`

- Make the app shell responsible for structure only.
- Keep the header and routed slot in the same document scroll flow.
- Avoid wrapping the slot in a fixed-height scroll container.

## Desktop Behavior

- Sidebar is visible by default on desktop.
- Sidebar can still collapse to icon mode.
- Main content scrolls with the document.
- Sidebar/footer/user controls remain accessible without a second scrollbar.

## Acceptance Criteria

- Desktop shows no inner vertical scrollbar beside the document scrollbar.
- Collapsing and expanding the sidebar does not change page scroll ownership.
- Header, breadcrumbs, page tabs, and page content remain aligned.
- Trip show and trip index pages still render correctly at desktop widths.

