# Phase 07 - Polish, Reporting, And Hardening

## Status

- Phase id: `phase-07-polish-reporting-hardening`
- Status: `planned`
- Depends on: Phase 06
- Blocks: Production use
- Completion gate: The app is reliable, fast, accessible, secure, and comfortable for repeated personal trip use.

## Goal

Turn the completed feature set into a dependable application. This phase focuses on refinement, reporting, performance, accessibility, security, and operational maintenance.

## Features

- Trip summary report with itinerary, reservations, budget, tasks, documents, and contacts.
- Print-friendly and PDF-friendly trip views.
- Past trip archive with memories, notes, and actual cost summaries.
- Global search across trips.
- Advanced filters for reservations, documents, and tasks.
- Accessibility audit and keyboard workflow cleanup.
- Responsive mobile refinements for travel-day use.
- Performance improvements for large trips.
- Data export for user ownership and backup.
- Account-level settings for home time zone, default currency, traveler profiles, and packing templates.

## Backend Work

- Add export services for trip data.
- Add reporting queries and summary endpoints.
- Add indexes for common trip, date, and reservation queries.
- Add cleanup jobs for expired invites and stale import batches.
- Add security review for document access and collaborator permissions.
- Add backup/export tests.

## Frontend Work

- Add printable trip summary.
- Add global search UI.
- Add account preferences for travel defaults.
- Refine mobile and desktop layouts.
- Add loading, empty, error, and permission states consistently.
- Complete accessibility and keyboard interaction passes.

## Tests

- Regression tests for permissions and private documents.
- End-to-end smoke tests for the main trip lifecycle.
- Accessibility checks for key pages.
- Performance checks for trips with many itinerary items and reservations.
- Export tests.

## Acceptance Criteria

- A user can export or print a complete trip plan.
- Main workflows remain fast with large trips.
- Mobile travel-day mode is readable and efficient.
- Accessibility issues from the audit are fixed or documented.
- Tests pass for the full application.

## Launch Checklist

- Database migrations are reversible where practical.
- Seeders provide realistic demo trips.
- Error pages and empty states are polished.
- Logs do not expose private travel details.
- File uploads are validated and storage is private.
- Backups and data export are documented.
- `STATE.md` marks every phase as `verified` or clearly documents remaining gaps.
