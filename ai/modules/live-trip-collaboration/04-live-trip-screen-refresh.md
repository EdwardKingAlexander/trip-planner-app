# Phase 04 - Live Trip Screen Refresh

## Status

- Status: `planned`
- Priority: `high`
- Depends on: Phase 03

## Goal

Refresh the open trip screen automatically when another participant changes the trip, while preserving the current panel, scroll position, and in-progress form state.

## Implementation Plan

1. Add a composable such as `resources/js/composables/useTripRealtime.ts`.
2. Subscribe to the trip private channel or fallback poller when `Trips/Show.vue` is mounted.
3. On remote trip changes:
   - show a small toast/notification
   - preserve the active panel
   - call `router.reload({ only: ['trip'], preserveScroll: true })`
   - avoid reload loops for events created by the current user
4. Map `changed_area` values to panels:
   - `itinerary`
   - `reservations`
   - `budget`
   - `packing`
   - `tasks`
   - `documents`
   - `imports`
   - `sharing`
5. Add subtle section-level changed indicators so the user can see what updated.

## Affected Files

- `resources/js/pages/Trips/Show.vue`
- `resources/js/composables/useTripRealtime.ts`
- `resources/js/lib/flashToast.ts`
- `resources/js/types/trips.ts`
- `resources/js/actions/*` and `resources/js/routes/*` if route generation changes

## Verification

- Frontend type check with `npm run types:check`.
- Lint check with `npm run lint:check`.
- Feature test that `TripController::show()` returns enough event/version metadata for partial reload decisions if fallback mode is used.
