# Phase 06 - Imports, Integrations, And Automation

## Status

- Phase id: `phase-06-imports-integrations-automation`
- Status: `verified`
- Depends on: Phase 05
- Blocks: Phase 07
- Completion gate: Users can import common travel details and use automation to reduce manual trip entry.

## Goal

Reduce manual data entry by adding imports and selected integrations. This phase should be driven by real user workflow value, not integration novelty.

## Features

- Confirmation email parsing workflow.
- PDF or image confirmation upload with manual extraction review.
- Calendar export for itinerary and reservations.
- ICS import for existing travel events.
- Map link normalization.
- Optional currency conversion for budget summaries.
- Optional live flight status integration.
- Duplicate detection for imported reservations.
- Review screen before imported data is committed.
- Automation suggestions: create reminders, add packing templates, flag missing hotel for a night, flag missing transport after arrival.

## Integration Candidates

- Email import: manual forward/upload first, mailbox integration later.
- Calendar: ICS export/import before direct calendar API sync.
- Flight status: use a paid or free aviation API only after confirming cost and reliability.
- Maps: store links first, add structured map APIs only if needed.
- Currency: daily exchange rate provider for estimates.

## Backend Work

- Add import batch model and review state.
- Add parsers for structured ICS and simple text confirmations.
- Add file processing jobs.
- Add duplicate detection service.
- Add calendar export endpoint.
- Add integration settings storage.

## Data Model

### `trip_import_batches`

- `id`
- `trip_id`
- `user_id`
- `source_type`
- `status`
- `original_file_path`
- `raw_text`
- `parsed_payload`
- `reviewed_at`
- `created_at`
- `updated_at`

### `trip_automation_suggestions`

- `id`
- `trip_id`
- `suggestion_type`
- `summary`
- `payload`
- `accepted_at`
- `dismissed_at`
- `created_at`
- `updated_at`

## Frontend Work

- Add import center inside trip settings or documents.
- Add upload and paste-confirmation flows.
- Add review UI for parsed reservations and itinerary items.
- Add calendar export actions.
- Add automation suggestion cards in trip overview.

## Tests

- Parser tests for sample confirmations and ICS files.
- Feature tests for import review and commit.
- Tests for duplicate detection.
- Tests for automation suggestions.
- Authorization tests for import batches.

## Acceptance Criteria

- A user can import an ICS file and review entries before adding them to a trip.
- A user can upload or paste a confirmation and convert parsed details into a reservation after review.
- Imported entries do not silently overwrite existing plans.
- Calendar export produces a usable file.
- Tests pass for the implemented scope.

## Notes For Later Phases

- Avoid autonomous booking changes. Automation should suggest, draft, and assist; the user confirms before trip data changes.
