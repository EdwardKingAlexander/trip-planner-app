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

## Themes Plan

Use [themes-plan](themes-plan/00-master-plan.md) for the five premade visual themes (Coastal, Sunset, Forest, Midnight, Sandstone), the theme registry/composable, persistence (cookie + DB), and the appearance settings picker. Its durable state file is `ai/state/themes-plan.json`.

## Navigation Uplift

Use [navigation-uplift](navigation-uplift/00-master-plan.md) to fix the broken sidebar links (six items currently route to `/trips`), wire Wayfinder-typed routes, add Calendar and Reminders global pages plus a Notifications nav entry, and bring the mobile sheet to parity with the sidebar. Its durable state file is `ai/state/navigation-uplift.json`.

## Notification Deep Links

Use [notification-deep-links](notification-deep-links/00-master-plan.md) to make notifications open the actual changed entity (right tab, scrolled into view, briefly highlighted) instead of dumping the user on the trip overview. Adds a `NotificationDeepLinkResolver`, a `read-and-go` endpoint, and trip-show focus targeting. Its durable state file is `ai/state/notification-deep-links.json`.

## Packing Checkbox

Use [packing-checkbox](packing-checkbox/00-master-plan.md) to add a single-click inline checkbox on packing items (currently users must enter edit mode and submit a form), with optimistic UI, a focused toggle endpoint, a progress header, and a "hide packed" filter. Its durable state file is `ai/state/packing-checkbox.json`.

## Packing Attribution

Use [packing-attribution](packing-attribution/00-master-plan.md) to attach a real "added by" and "for whom" identity to every packing item on a shared trip, send a personal notification to the adder when the assigned user packs (or unpacks) it, and surface per-assignee progress segments plus a "Mine to pack" filter. Its durable state file is `ai/state/packing-attribution.json`.

## Document Uploads

Use [document-uploads](document-uploads/00-master-plan.md) to turn the text-only Documents tab into a real attachment surface — multi-file PDF and image uploads with image thumbnails, PDF new-tab preview, per-reservation attachments section with auto-link, and a private file-serving endpoint gated by the trip view policy. Its durable state file is `ai/state/document-uploads.json`.

## Flight Details

Use [flight-details](flight-details/00-master-plan.md) to give every flight reservation an optional sidecar covering cabin class, baggage allowance + fees per bag type (carry-on, personal item, checked, additional), trip-aware currency, visa requirement, passport validity rule, layover/connection notes, online check-in opens, boarding closes, and a free-text notes block — all nullable, with lazy create/delete and a one-line read-only summary on the reservation card. Its durable state file is `ai/state/flight-details.json`.

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
