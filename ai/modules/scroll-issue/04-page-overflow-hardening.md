# Phase 04 - Page Overflow Hardening

## Goal

Remove page-level overflow traps that remain after the shell has one clear scroll owner.

## Files To Harden

- `resources/js/pages/Trips/Index.vue`
- `resources/js/pages/Trips/Show.vue`
- `resources/js/pages/Trips/Search.vue`
- `resources/js/pages/settings/TravelPreferences.vue`
- `resources/js/layouts/settings/Layout.vue`
- `resources/js/components/Breadcrumbs.vue`
- `resources/js/components/NavMain.vue`

## Planned Changes

- Remove unnecessary `overflow-y-auto`, `overflow-auto`, fixed height, and viewport-height wrappers from routed pages.
- Keep horizontal overflow controlled on tables, dense tabs, and action rows without creating vertical scroll traps.
- Ensure trip tabs wrap or scroll horizontally only when needed.
- Ensure cards and side panels grow naturally with content.
- Use `min-w-0`, responsive grids, and full-width sections to prevent layout blowouts.

## Design Requirements

- Preserve the more uplifting visual direction from the responsive UI uplift.
- Keep operational screens compact and scannable.
- Avoid nested cards and decorative layout noise.
- Maintain touch-friendly spacing on mobile while keeping desktop information dense.

## Acceptance Criteria

- Long trip show content scrolls as one continuous page.
- Settings pages do not introduce their own page-level scrollbar.
- Horizontal controls do not create vertical nested scrolling.
- Empty states, forms, and side panels remain readable on mobile and desktop.

