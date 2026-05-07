# Navigation Uplift Master Plan

## Goal

Fix the broken sidebar links and bring the navigation surface up to a level the rest of the app already operates at: typed routes, accurate active state, real destinations for every visible item, and parity between desktop sidebar and mobile sheet.

## Status

- Status: `verified`
- State file: `ai/state/navigation-uplift.json`
- Last updated: 2026-05-07

## Problem

Today the authenticated sidebar shows eight items but only two actually navigate somewhere distinct.

| Sidebar item | Current `href` | Real destination today | Verdict |
| --- | --- | --- | --- |
| Trips | `/trips` | `trips.index` | Works |
| Search | `/trips/search` | `trips.search` | Works |
| Calendar | `/trips` | `trips.index` (silently) | Broken — wrong destination |
| Reservations | `/trips` | `trips.index` (silently) | Broken — wrong destination |
| Packing | `/trips` | `trips.index` (silently) | Broken — wrong destination |
| Documents | `/trips` | `trips.index` (silently) | Broken — wrong destination |
| Sharing | `/trips` | `trips.index` (silently) | Broken — wrong destination |
| Reminders | `/trips` | `trips.index` (silently) | Broken — wrong destination |

Confirmed in `resources/js/components/AppSidebar.vue:29-70`. The six broken items all share the same `href`, so they also defeat `isCurrentUrl()` — the "Trips" item lights up regardless of which one was clicked, which is why the user perceives the click as a no-op.

Adjacent issues found during the audit:

- `AppHeader.vue:64-75` still carries the Laravel starter-kit `Repository` and `Documentation` external links plus a `Search` button with no `@click` handler.
- The mobile `Sheet` menu inside `AppHeader.vue` only renders `mainNavItems` from the header file (just `Dashboard`), not the real sidebar item list — so mobile users see a different navigation than desktop.
- `notifications.index` exists as a real route but is not exposed in the navigation anywhere.
- Sidebar items use raw string `href` values while the rest of the codebase has standardized on Wayfinder typed routes (`@/routes`, `@/actions`).

## Strategy

Two-track repair: **fix what's wrong** (broken hrefs, dead controls, mismatched menus) and **straighten the information architecture** (what belongs in the global nav vs. what belongs inside a trip).

Recommended final global nav (subject to confirmation in Phase 01):

- Trips, Search, Calendar (cross-trip), Reminders (cross-trip), Notifications.

Items that are inherently scoped to a single trip (Reservations, Packing, Documents, Sharing) move out of the global sidebar and stay as in-trip tabs where they already live.

## Phases

1. [Navigation Audit And Information Architecture](01-navigation-audit-and-information-architecture.md)
2. [Typed Routes And Active State](02-typed-routes-and-active-state.md)
3. [Global Pages And Nav Cleanup](03-global-pages-and-nav-cleanup.md)
4. [Header And Mobile Sheet Parity](04-header-and-mobile-sheet-parity.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Run phases in order. Phase 01 freezes the IA decision so the rest of the work has a target. Phase 02 makes active-state work correctly so Phase 03 can build/remove pages without fighting the highlight logic. Phase 04 is the cross-cutting cleanup (header + mobile parity). Phase 05 is the gate.

## Acceptance Criteria

- Every visible navigation item routes to a distinct, real destination — no two items share an `href`.
- The active-state ring/underline matches the current URL exactly: only one nav item is highlighted at a time.
- Mobile sheet menu and desktop sidebar render the same item list with the same active behavior.
- No dead links, no `<button>` triggers without handlers, no starter-kit `Repository` / `Documentation` external links shipped to authenticated users.
- All nav `href` values are produced by Wayfinder-typed route helpers, not raw strings.
- `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, and `vendor/bin/pint --dirty --format agent` pass.

## Out Of Scope

- A global command palette / spotlight search (separate effort — the existing `/trips/search` page is sufficient).
- Reordering or restyling navigation visuals beyond what is needed to land the IA changes.
- Permissions-aware nav (showing/hiding items based on role) — the app is single-tenant personal use.
- Marketing/unauthenticated nav.
