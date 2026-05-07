# Phase 01 - Navigation Audit And Information Architecture

## Goal

Lock the final global navigation list and decide, item by item, what each one resolves to. No code changes in this phase — its output is the IA contract every other phase consumes.

## Status

- Status: `verified`
- Owner: codex
- Depends on: none
- Blocker: none

## Audit Inputs

- `resources/js/components/AppSidebar.vue:29-70` — current sidebar items.
- `resources/js/components/AppHeader.vue:56-75` — starter-kit header items.
- `php artisan route:list --except-vendor` — confirmed authoritative route surface.

### Routes that exist today (relevant subset)

| URI | Name |
| --- | --- |
| `GET /trips` | `trips.index` |
| `GET /trips/create` | `trips.create` |
| `GET /trips/search` | `trips.search` |
| `GET /trips/{trip}` | `trips.show` |
| `GET /trips/{trip}/edit` | `trips.edit` |
| `GET /trips/{trip}/print` | `trips.print` |
| `GET /trips/{trip}/export.ics` | `trips.export.ics` |
| `GET /trips/{trip}/export.json` | `trips.export.json` |
| `GET /notifications` | `notifications.index` |
| `GET /settings/profile` | `profile.edit` |
| `GET /settings/security` | `security.edit` |
| `GET /settings/travel` | `travel-preferences.edit` |
| `ANY /dashboard` | `dashboard` (redirect) |

No global routes exist for `calendar`, `reservations`, `packing`, `documents`, `sharing`, or `reminders`. Every reservation/packing/document/collaborator/reminder route is nested under `trips/{trip}/...`.

## Decision Matrix

| Sidebar item today | Disposition | Reasoning |
| --- | --- | --- |
| Trips | Keep, route to `trips.index` | Anchor of the app |
| Search | Keep, route to `trips.search` | Already works |
| Calendar | Keep, build new global page (Phase 03) | Cross-trip view is a real product need |
| Reminders | Keep, build new global page (Phase 03) | Reminders span trips and benefit from a cross-trip inbox |
| Notifications | Add, route to `notifications.index` | Endpoint exists; surfacing it costs nothing |
| Reservations | Remove from global nav | Inherently per-trip; lives as a trip tab |
| Packing | Remove from global nav | Per-trip |
| Documents | Remove from global nav | Per-trip |
| Sharing | Remove from global nav | Per-trip; collaborator routes are nested |

If the user prefers to keep Reservations / Packing / Documents / Sharing in the sidebar, the alternative is to build cross-trip aggregator pages for each. That doubles Phase 03 scope and is recorded as the opt-in fork in the state file.

## Final Global Nav (proposed)

1. Trips
2. Search
3. Calendar
4. Reminders
5. Notifications

Order matches the user's likely traversal: pick a trip → search across trips → see what's coming up → see what to act on → see what changed.

## Open Questions To Resolve Before Phase 02

- Confirm the five-item list above with the user.
- Confirm Calendar should be a true cross-trip month view rather than a redirect to the most recent trip's itinerary.
- Confirm the global Reminders page is desired over a Reminders tab inside each trip.

Record the answers in `ai/state/navigation-uplift.json` under `decisions[]` before starting Phase 02.

## State Management

- This phase produces a written contract only. The state file is the authoritative record for the IA decision; the master plan is the human-readable summary; phase 02 reads from the state file when it builds the typed item list.

## Acceptance Criteria

- The final nav list, in order, is recorded in the state file.
- Each item has a recorded target route name and a target Wayfinder import path.
- Each removed item has a recorded "where to find it instead" answer (e.g., "Reservations live as a tab on the trip show page").
- The user has signed off on the list.

## Out Of Scope

- Building any of the new pages (Phase 03).
- Touching `AppSidebar.vue`, `AppHeader.vue`, or `NavMain.vue` (Phases 02 / 04).
