# Phase 05 - Sharing, Notifications, And Travel Day Operations

## Status

- Phase id: `phase-05-sharing-notifications-operations`
- Status: `verified`
- Depends on: Phase 04
- Blocks: Phase 06
- Completion gate: Users can share trips, receive reminders, and use a focused travel-day mode for time-sensitive information.

## Goal

Make the app useful during real travel and support collaboration with other travelers. This phase adds sharing, reminders, notifications, and an operational view for the current day.

## Features

- Trip collaborators with roles: owner, editor, viewer.
- Invite flow by email.
- Shared trip permissions for itinerary, reservations, documents, budget, and settings.
- Reminder rules for flights, check-in, checkout, activities, tasks, passport expiration, and custom events.
- Notification delivery through in-app notifications and email.
- Travel-day mode showing today, tomorrow, active reservations, next event, addresses, confirmation numbers, and documents.
- Offline-friendly cache plan for travel-day essentials.
- Conflict warnings for overlapping reservations or impossible travel gaps.

## Data Model

### `trip_collaborators`

- `id`
- `trip_id`
- `user_id`
- `email`
- `role`
- `accepted_at`
- `created_at`
- `updated_at`

### `trip_reminders`

- `id`
- `trip_id`
- `remindable_type`
- `remindable_id`
- `label`
- `remind_at`
- `timezone`
- `delivery_channels`
- `sent_at`
- `created_at`
- `updated_at`

### `trip_activity_events`

- `id`
- `trip_id`
- `user_id`
- `event_type`
- `summary`
- `metadata`
- `created_at`
- `updated_at`

## Backend Work

- Add collaboration policies and role-aware authorization.
- Add invitation and acceptance workflow.
- Add reminder scheduling jobs.
- Add notification classes.
- Add conflict detection service.
- Add travel-day read model or optimized endpoint.

## Frontend Work

- Add share settings panel.
- Add collaborator list and invite form.
- Add notification center or notification menu.
- Add reminder controls on itinerary items, reservations, and tasks.
- Add travel-day route optimized for mobile.
- Add warning surfaces for conflicts.

## Tests

- Feature tests for collaborator roles.
- Tests for invitation acceptance and rejection.
- Job tests for reminder scheduling and send behavior.
- Conflict detection tests.
- Travel-day endpoint authorization tests.

## Acceptance Criteria

- Owners can invite collaborators and assign roles.
- Editors can update allowed trip data; viewers cannot edit.
- Reminders can be scheduled and marked sent.
- Travel-day mode shows the next relevant event and critical details.
- Tests pass for the implemented scope.

## Notes For Later Phases

- Push notifications can wait unless the app is later packaged as a PWA or mobile app. Email and in-app notifications are enough for this phase.
