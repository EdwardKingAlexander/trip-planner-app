# Phase 02 - Notification Center And Read State

## Status

- Status: `planned`
- Priority: `high`
- Depends on: Phase 01

## Goal

Create Facebook-style in-app notifications for trip collaborators, including unread state and a compact UI surface visible from authenticated pages.

## Implementation Plan

1. Add Laravel database notification support if the `notifications` table is still absent.
2. Create a notification class such as `App\Notifications\TripChangedNotification`.
3. Notify every eligible trip participant except the actor.
4. Include payload fields for `trip_id`, `trip_name`, `actor_name`, `changed_area`, `summary`, `event_id`, and optional `anchor`.
5. Share unread notification summary data through `HandleInertiaRequests`.
6. Add notification routes for listing recent notifications and marking one/all as read.
7. Add a header notification bell/dropdown in the authenticated layout using the app's current travel styling.

## Affected Files

- `database/migrations/*_create_notifications_table.php`
- `app/Notifications/TripChangedNotification.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Controllers/NotificationController.php`
- `routes/web.php`
- `resources/js/layouts/AppLayout.vue`
- `resources/js/components/*`
- `resources/js/types/*`
- `tests/Feature/TripNotificationTest.php`

## Verification

- Feature test notification fan-out to owner and collaborators, excluding the actor.
- Feature test read/unread routes.
- Frontend type check for shared notification props.
- Run `php artisan test --compact tests/Feature/TripNotificationTest.php`.
