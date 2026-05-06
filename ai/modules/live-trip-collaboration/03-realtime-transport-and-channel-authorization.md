# Phase 03 - Realtime Transport And Channel Authorization

## Status

- Status: `planned`
- Priority: `high`
- Depends on: Phase 02

## Goal

Deliver push notifications and trip-change events over private authenticated channels so attached users receive updates immediately.

## Preferred Implementation

1. Add Laravel Reverb server support and JavaScript Echo client dependencies after dependency approval.
2. Configure broadcasting environment values and document required local/production variables.
3. Add `routes/channels.php` authorization for:
   - `private-App.Models.User.{id}` notification channels
   - `private-trips.{tripId}` trip collaboration channels
4. Authorize trip channels with `Trip::visibleTo($user)`.
5. Broadcast a dedicated event such as `TripChanged` after activity events are committed.
6. Use `toOthers()` where appropriate so the actor does not receive duplicate live updates.

## Fallback Implementation

If new dependencies are deferred, use Inertia `usePoll()` on authenticated layouts and trip show pages:

- Poll recent notifications.
- Poll trip change version metadata.
- Trigger partial reloads when a newer event is detected.

## Affected Files

- `composer.json`
- `package.json`
- `.env.example`
- `config/broadcasting.php`
- `routes/channels.php`
- `bootstrap/app.php`
- `resources/js/bootstrap.ts` or `resources/js/app.ts`
- `app/Events/TripChanged.php`
- `tests/Feature/TripBroadcastChannelTest.php`

## Verification

- Feature test channel authorization for owner, collaborator, and stranger.
- Event fake test that trip mutations broadcast only after successful writes.
- Frontend type/lint check for Echo setup.
- Run `php artisan test --compact tests/Feature/TripBroadcastChannelTest.php`.
