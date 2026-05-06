# Phase 01 - Entry Editing Backend

## Status

- Status: `verified`
- Priority: `high`

## Goal

Add safe update endpoints for trip subresources and ensure every editable planning entry has a notes-capable backend field.

## Implementation Plan

1. Add a migration for `trip_reminders.notes`.
2. Update `TripReminder` fillable fields.
3. Add update methods for:
   - itinerary items
   - reservations
   - costs
   - packing items
   - tasks
   - documents
   - reminders
4. Add route definitions with parent trip scoping.
5. Validate that each edited model belongs to the trip before updating.
6. Keep specialized reservation details in sync when flight/lodging fields change.

## Verification

- Added feature tests for owner/editor update success.
- Added feature tests for viewer rejection.
- Passed `php artisan test --compact tests/Feature/TripManagementTest.php` with 6 tests and 32 assertions.
