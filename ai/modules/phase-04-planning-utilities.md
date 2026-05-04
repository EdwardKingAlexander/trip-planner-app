# Phase 04 - Planning Utilities

## Status

- Phase id: `phase-04-planning-utilities`
- Status: `verified`
- Depends on: Phase 03
- Blocks: Phase 05
- Completion gate: Budget, packing, documents, and checklist tools are available inside each trip.

## Goal

Add the practical planning utilities that sit around the itinerary: budget tracking, packing lists, documents, tasks, notes, and travel requirements.

## Features

- Budget categories for flights, lodging, food, activities, transport, shopping, fees, insurance, and custom costs.
- Planned versus actual cost tracking.
- Currency per cost entry.
- Split cost notes for travelers.
- Packing lists with templates and per-traveler sections.
- Trip tasks with due dates and completion status.
- Document vault for passports, IDs, tickets, confirmations, visas, insurance, and custom files.
- Notes organized by trip, day, reservation, or custom category.
- Travel requirement tracker for visas, vaccines, passport expiration, entry forms, and local rules.

## Data Model

### `trip_costs`

- `id`
- `trip_id`
- `reservation_id`
- `category`
- `label`
- `planned_amount`
- `actual_amount`
- `currency`
- `paid_at`
- `notes`
- `created_at`
- `updated_at`

### `packing_items`

- `id`
- `trip_id`
- `traveler_name`
- `category`
- `label`
- `quantity`
- `is_packed`
- `sort_order`
- `notes`
- `created_at`
- `updated_at`

### `trip_tasks`

- `id`
- `trip_id`
- `title`
- `description`
- `due_at`
- `completed_at`
- `priority`
- `created_at`
- `updated_at`

### `trip_documents`

- `id`
- `trip_id`
- `reservation_id`
- `title`
- `document_type`
- `file_path`
- `expires_on`
- `notes`
- `created_at`
- `updated_at`

## Backend Work

- Add models, migrations, policies, controllers, and validation.
- Add secure storage rules for uploaded documents.
- Add budget summary queries.
- Add reusable checklist logic for tasks and packing items.

## Frontend Work

- Add budget tab with summaries, category breakdown, and cost entry forms.
- Add packing tab with checkable list interactions.
- Add documents tab with upload, preview metadata, and reservation linking.
- Add tasks tab or trip overview task panel.
- Keep dense operational pages clear and scannable.

## Tests

- Feature tests for budget CRUD and summaries.
- Feature tests for packing list updates.
- File upload tests for document storage authorization.
- Tests for task completion and due-date filtering.

## Acceptance Criteria

- A user can track planned and actual costs by category.
- A user can maintain packing and task checklists.
- A user can upload documents and link them to reservations.
- Private files are not accessible to other users.
- Tests pass for the implemented scope.

## Notes For Later Phases

- Multi-currency conversion can be added in Phase 06. This phase should store currency accurately and summarize only like-for-like currencies unless conversion data exists.
