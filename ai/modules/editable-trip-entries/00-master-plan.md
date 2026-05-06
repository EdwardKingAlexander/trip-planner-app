# Editable Trip Entries Plan

## Status

- Module id: `editable-trip-entries`
- Status: `verified`
- Current phase: `03-verification-and-state-handoff`
- State file: `ai/state/editable-trip-entries.json`
- Last updated: `2026-05-06`

## Purpose

Give itinerary items, reservations, budget costs, and the surrounding trip planning entries visible notes plus the ability to edit after creation. This closes the current create-only workflow where users must delete/recreate or live with mistakes.

## Existing Grounding

- `itinerary_items` already has `description`, which will serve as the visible notes field for itinerary entries.
- `reservations`, `trip_costs`, `packing_items`, and `trip_documents` already have `notes`.
- `trip_tasks` already has `description`, which will serve as task notes.
- `trip_reminders` currently has no notes column and needs a small forward migration.
- Create-only routes currently live in `routes/web.php`.
- The main UI surface is `resources/js/pages/Trips/Show.vue`.

## Phase Index

1. [Phase 01 - Entry Editing Backend](01-entry-editing-backend.md)
2. [Phase 02 - Notes Fields And Edit UI](02-notes-fields-and-edit-ui.md)
3. [Phase 03 - Verification And State Handoff](03-verification-and-state-handoff.md)

## Target Workflows

- Edit itinerary item title, type, time, location, status, and notes.
- Edit reservation title, provider, confirmation, status, dates, contact/location details, specialized flight/lodging details, and notes.
- Edit budget cost category, label, planned amount, actual amount, currency, and notes.
- Edit packing item traveler, category, label, quantity, packed state, and notes.
- Edit task title, due date, priority, completion state, and notes.
- Edit document title, type, expiration date, and notes.
- Edit reminder label, reminder date/time, timezone, and notes.

## Guardrails

- Reuse existing columns where possible instead of inventing duplicate note fields.
- Keep edit routes scoped through the parent trip and authorize through the existing `TripPolicy`.
- Preserve collaborator editor behavior and prevent viewers/strangers from editing.
- Keep frontend changes inside the existing trip detail page rather than adding separate edit pages.
- Run focused backend tests, route generation, PHP formatting, and frontend type/build checks before completion.
