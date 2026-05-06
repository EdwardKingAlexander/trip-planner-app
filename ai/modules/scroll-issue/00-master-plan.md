# Scroll Issue Master Plan

## Problem

The app currently allows more than one vertical scroll owner at the same time. The screenshot shows a page/document scrollbar and an inner application scrollbar. That creates a bad touch experience on desktop trackpads and mobile screens because the user can reach the end of one scroll area, then has to release and scroll again before the outer page responds.

## Status

- Status: `verified_programmatic`
- State file: `ai/state/scroll-issue.json`
- Last updated: `2026-05-06`

## Root Cause

- `resources/js/components/ui/sidebar/Sidebar.vue` renders the desktop sidebar as a fixed `h-svh` element.
- `resources/js/components/ui/sidebar/SidebarContent.vue` uses `overflow-auto`, so the sidebar can become its own scroll area.
- `resources/js/components/ui/sidebar/SidebarInset.vue` and the routed page content can also grow and scroll independently from the sidebar shell.
- `resources/js/layouts/app/AppSidebarLayout.vue` combines a fixed sidebar, header, and page slot in a way that can produce competing scroll containers.
- Mobile should not expose the persistent sidebar as part of the page layout. It should be hidden by default and opened through a collapsible/offcanvas menu.

## Target Behavior

- Desktop has one primary vertical scroll owner: the document/page.
- Desktop sidebar remains usable and collapsible without introducing a second vertical scrollbar.
- Mobile and tablet hide the sidebar by default.
- Mobile navigation opens from the existing sidebar trigger as an offcanvas menu.
- Opening the mobile menu does not require the user to scroll an inner page and then resume the outer page.
- Long trip pages, settings pages, and authenticated screens scroll continuously from top to bottom.
- No database migrations or destructive database commands are needed.

## Phases

1. [Scroll Ownership Audit](01-scroll-ownership-audit.md)
2. [Desktop Single-Scroll Shell](02-desktop-single-scroll-shell.md)
3. [Mobile Offcanvas Navigation](03-mobile-offcanvas-navigation.md)
4. [Page Overflow Hardening](04-page-overflow-hardening.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Complete the phases in order. The audit comes first because it identifies every `overflow`, `height`, and fixed-position rule that can create a nested scroll. The desktop shell and mobile navigation changes should be done before page-level cleanup so page fixes are not masking shell problems.

## Acceptance Criteria

- At `1440px`, the trip show page has only one visible vertical scrollbar.
- At tablet width, the sidebar is not a persistent page column unless explicitly opened.
- At mobile width, the side menu is hidden by default and opens from a trigger.
- Scrolling a long trip page works with one continuous gesture.
- The mobile menu closes after selecting a navigation item.
- Browser console is free of hydration, layout, and Vue runtime errors.
- Build and focused tests pass.
