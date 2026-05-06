# Vacation Plan App Modules

This directory is the planning and build-control area for the vacation trip management application.

## Build Order

1. [Phase 01 - Product Foundation](phase-01-product-foundation.md)
2. [Phase 02 - Trip Core And Itinerary](phase-02-trip-core-and-itinerary.md)
3. [Phase 03 - Reservations, Dates, And Times](phase-03-reservations-dates-and-times.md)
4. [Phase 04 - Planning Utilities](phase-04-planning-utilities.md)
5. [Phase 05 - Sharing, Notifications, And Travel Day Operations](phase-05-sharing-notifications-operations.md)
6. [Phase 06 - Imports, Integrations, And Automation](phase-06-imports-integrations-automation.md)
7. [Phase 07 - Polish, Reporting, And Hardening](phase-07-polish-reporting-hardening.md)

## Production Feedback

Use [production-feedback](production-feedback/00-master-plan.md) for live production testing feedback, fixes, enhancements, and release handoff state. Its durable state file is `ai/state/production-feedback.json`.

## Live Trip Collaboration

Use [live-trip-collaboration](live-trip-collaboration/00-master-plan.md) for realtime shared-trip notifications, private broadcast channel planning, and automatic trip screen updates. Its durable state file is `ai/state/live-trip-collaboration.json`.

## Editable Trip Entries

Use [editable-trip-entries](editable-trip-entries/00-master-plan.md) for post-creation editing and visible notes on itinerary, reservations, budget, packing, tasks, documents, and reminders. Its durable state file is `ai/state/editable-trip-entries.json`.

## Scroll Issue

Use [scroll-issue](scroll-issue/00-master-plan.md) for authenticated layout scroll ownership, sidebar/offcanvas behavior, and page overflow hardening. Its durable state file is `ai/state/scroll-issue.json`.

## State Tracking

Use [STATE.md](STATE.md) as the single source of truth for implementation status. Each phase file also has a phase-local status block that should be updated as work moves from planning to implementation, verification, and completion.

## Status Values

- `planned`: scoped but not started
- `in_progress`: actively being built
- `blocked`: waiting on a dependency or decision
- `implemented`: code is complete but not fully verified
- `verified`: tests and manual checks passed
- `shipped`: accepted as complete

## Product Assumptions

- The app is a personal vacation management app with authenticated users.
- A user can manage multiple trips, each with dates, destinations, travelers, reservations, itinerary items, documents, costs, reminders, and notes.
- Flight, hotel, transport, activity, dining, and custom itinerary entries must support dates, local times, time zones, confirmation details, and attachments.
- The current stack is Laravel, Inertia, Vue, TypeScript, Tailwind, and Pest.
