# Phase 01 - Product Foundation

## Status

- Phase id: `phase-01-product-foundation`
- Status: `verified`
- Depends on: Existing Laravel/Inertia/Vue auth starter
- Blocks: All later phases
- Completion gate: Authenticated users can create, list, view, edit, archive, and delete their own trips from a functional app shell.

## Goal

Establish the product foundation for a personal vacation planning app. This phase turns the starter dashboard into a trip-focused workspace with the minimum data model, navigation, and CRUD workflows needed for future itinerary and reservation features.

## Features

- Trip dashboard with upcoming, active, past, archived, and draft trips.
- Trip create/edit forms with name, destination summary, start date, end date, cover image color or preset, notes, and status.
- Trip detail shell with tabs or sections for overview, itinerary, reservations, budget, packing, documents, and settings.
- Trip ownership scoped to the authenticated user.
- Empty states for users with no trips.
- Basic search and filters by destination, status, and date range.
- Soft-delete or archive behavior so old trips are recoverable.

## Data Model

### `trips`

- `id`
- `user_id`
- `name`
- `destination`
- `starts_on`
- `ends_on`
- `status`: `draft`, `planned`, `active`, `completed`, `archived`
- `summary`
- `cover_theme`
- `created_at`
- `updated_at`
- `deleted_at`

## Backend Work

- Add `Trip` model, migration, factory, policy, and controller.
- Add request validation for create/update.
- Add web routes behind auth middleware.
- Scope every trip query to the current user.
- Add basic authorization tests.

## Frontend Work

- Replace the generic dashboard with a trip dashboard.
- Add trip index, create, edit, show, and settings views.
- Update main navigation with Trips, Calendar, Budget, Packing, Documents, and Settings placeholders.
- Use existing UI components and layout conventions.
- Keep the first screen operational instead of marketing-oriented.

## Tests

- Feature tests for trip CRUD.
- Policy tests for user isolation.
- Validation tests for required dates and date order.
- Type check for Vue pages and shared trip types.

## Acceptance Criteria

- A signed-in user can create a trip with a valid date range.
- Trips appear in a dashboard grouped by trip timing.
- A user cannot access another user's trips.
- Editing a trip updates details without breaking navigation.
- Archiving a trip removes it from the default upcoming list.
- Tests pass for the implemented scope.

## Notes For Later Phases

- Keep `starts_on` and `ends_on` as date-only trip boundaries. Precise travel times belong to itinerary and reservation records in later phases.
- Avoid adding trip sharing in this phase. The ownership model should support adding collaborators later without reworking every query.
