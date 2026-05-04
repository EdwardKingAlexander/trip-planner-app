# Phase 02 - Trip Core And Itinerary

## Status

- Phase id: `phase-02-trip-core-and-itinerary`
- Status: `verified`
- Depends on: Phase 01
- Blocks: Phase 03
- Completion gate: Trips have day-by-day itinerary planning with structured entries, calendar-style views, and reliable ordering.

## Goal

Create the core itinerary system. A trip should become a practical schedule where the user can plan each day, track fixed-time events, and capture flexible ideas.

## Features

- Itinerary days generated from trip start and end dates.
- Day view with ordered itinerary items.
- Timeline view for fixed-time entries.
- Unscheduled idea list for places or activities not yet assigned to a day.
- Entry categories: flight, lodging, ground transport, activity, dining, note, task, custom.
- Drag or action-based reordering within a day.
- Duplicate, move, and convert item actions.
- Time display with all-day, start-only, start/end, and flexible modes.
- Location fields for destination, venue, address, map link, and notes.

## Data Model

### `trip_days`

- `id`
- `trip_id`
- `date`
- `title`
- `notes`
- `sort_order`
- `created_at`
- `updated_at`

### `itinerary_items`

- `id`
- `trip_id`
- `trip_day_id`
- `type`
- `title`
- `description`
- `location_name`
- `address`
- `map_url`
- `starts_at`
- `ends_at`
- `is_all_day`
- `timezone`
- `sort_order`
- `status`: `idea`, `planned`, `booked`, `cancelled`, `completed`
- `created_at`
- `updated_at`

## Backend Work

- Add models, migrations, factories, policies, controllers, and validation.
- Regenerate or reconcile `trip_days` when trip dates change.
- Add service logic for safe item ordering and moving.
- Keep `starts_at` and `ends_at` nullable for flexible planning.

## Frontend Work

- Add itinerary tab to trip detail.
- Add day navigation with date labels and trip-relative day numbers.
- Add item create/edit drawer or modal.
- Add type icons using `lucide-vue-next`.
- Add compact list, detailed day, and timeline display modes.

## Tests

- Feature tests for creating, updating, moving, and deleting itinerary items.
- Tests for trip date changes and day regeneration.
- Validation tests for time ordering and required fields.
- Frontend type checks for itinerary item payloads.

## Acceptance Criteria

- A trip automatically exposes a day-by-day planning surface.
- A user can add items with or without exact times.
- Items remain ordered after edits and moves.
- Date changes preserve compatible itinerary items and flag out-of-range items.
- Tests pass for the implemented scope.

## Notes For Later Phases

- Phase 03 will add specialized reservation records. This phase should allow generic items but avoid overloading them with every flight and hotel field.
