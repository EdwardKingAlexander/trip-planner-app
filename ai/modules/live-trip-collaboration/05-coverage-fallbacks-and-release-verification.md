# Phase 05 - Coverage, Fallbacks, And Release Verification

## Status

- Status: `planned`
- Priority: `medium`
- Depends on: Phases 01-04

## Goal

Make the realtime collaboration work production-ready, testable, and resilient when websocket infrastructure is unavailable.

## Implementation Plan

1. Add a server-side feature flag or config value for realtime mode:
   - `broadcast`
   - `polling`
   - `off`
2. Ensure notification creation does not fail the original trip write if broadcasting is temporarily unavailable.
3. Add tests for core mutation surfaces beyond the first happy path.
4. Verify unread counts in the authenticated layout.
5. Verify trip page auto-refresh does not clear active local form input.
6. Update module state with the chosen transport, verification commands, and remaining operational notes.

## Verification Commands

- `php artisan test --compact tests/Feature/TripManagementTest.php`
- `php artisan test --compact tests/Feature/TripNotificationTest.php`
- `php artisan test --compact tests/Feature/TripBroadcastChannelTest.php`
- `npm run lint:check`
- `npm run types:check`
- `npm run build`
- `vendor/bin/pint --dirty --format agent` after PHP edits during implementation

## Release Notes To Capture

- Required realtime environment variables.
- Whether Reverb/Echo or polling fallback was implemented.
- Any queue worker requirements for notification and broadcast delivery.
- Known limits for offline/background tabs.
