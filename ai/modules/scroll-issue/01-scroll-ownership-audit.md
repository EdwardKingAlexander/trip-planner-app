# Phase 01 - Scroll Ownership Audit

## Goal

Find every layout rule that can create a second vertical scroll area before changing the shell. The result should be a short implementation checklist, not a broad redesign.

## Files To Inspect

- `resources/js/layouts/app/AppSidebarLayout.vue`
- `resources/js/components/AppShell.vue`
- `resources/js/components/AppContent.vue`
- `resources/js/components/AppSidebar.vue`
- `resources/js/components/AppSidebarHeader.vue`
- `resources/js/components/ui/sidebar/Sidebar.vue`
- `resources/js/components/ui/sidebar/SidebarContent.vue`
- `resources/js/components/ui/sidebar/SidebarInset.vue`
- `resources/js/components/ui/sidebar/SidebarProvider.vue`
- `resources/js/components/ui/sidebar/SidebarTrigger.vue`
- `resources/js/layouts/settings/Layout.vue`
- `resources/js/pages/Trips/Index.vue`
- `resources/js/pages/Trips/Show.vue`
- `resources/css/app.css`

## Audit Commands

Use `rg` to find the layout rules that usually create nested scrolling:

```bash
rg "overflow-|overflow:|h-svh|min-h-svh|max-h|fixed|sticky|Sheet|Sidebar" resources/js resources/css
```

Also inspect the generated page in the browser at:

- Desktop: `1440x900`
- Tablet: `820x1180`
- Mobile: `390x844`

## Decisions To Record

- Which component owns page scrolling.
- Whether the desktop sidebar should be normal-flow, sticky, or fixed.
- Which components are allowed to use vertical overflow. The default should be none, except modal/offcanvas overlays while they are open.
- Whether any page-level wrappers need `min-h-0`, `min-w-0`, or `overflow-visible` to prevent accidental scroll traps.

## Acceptance Criteria

- The implementation files that need edits are known.
- Every intentional scroll container is documented.
- Any existing page-level `overflow-y-auto` or `overflow-auto` is either justified or marked for removal.

