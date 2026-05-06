# Phase 01 - Collaboration Event Foundation

## Status

- Status: `planned`
- Priority: `high`
- Depends on: existing trip management tables and policies

## Goal

Centralize how trip changes are recorded and described so every mutation can produce the same activity, notification, and realtime payload.

## Implementation Plan

1. Add a small service such as `App\Services\TripCollaborationEventService`.
2. Reuse `trip_activity_events` as the durable activity log.
3. Define stable event fields:
   - `trip_id`
   - `actor_user_id`
   - `event_type`
   - `summary`
   - `changed_area`
   - `subject_type`
   - `subject_id`
   - `metadata`
4. Wrap mutation controllers so activity creation happens after the model write succeeds.
5. Avoid duplicate hand-built payloads by routing every controller through the service.
6. Keep authorization based on existing trip visibility and edit rules.

## Affected Files

- `app/Services/TripCollaborationEventService.php`
- `app/Models/TripActivityEvent.php`
- `app/Http/Controllers/TripController.php`
- `app/Http/Controllers/TripItineraryController.php`
- `app/Http/Controllers/TripReservationController.php`
- `app/Http/Controllers/TripPlanningController.php`
- `app/Http/Controllers/TripImportController.php`
- `app/Http/Controllers/TripCollaboratorController.php`
- `tests/Feature/TripManagementTest.php`

## Verification

- Add feature tests proving itinerary, reservation, and cost changes create activity events.
- Assert non-collaborators cannot create or receive events for another user's trip.
- Run `php artisan test --compact tests/Feature/TripManagementTest.php`.
