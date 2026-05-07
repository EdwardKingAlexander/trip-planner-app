# Phase 02 - Backend Subject Routing And Read-And-Go Endpoint

## Goal

Stand up the resolver, surface subject metadata in the notification serializer, and add a single `read-and-go` endpoint that marks a notification read AND redirects in one round trip.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01 (contract)
- Blocker: none

## Planned Changes

### `app/Services/NotificationDeepLinkResolver.php` (new)

- `__invoke(DatabaseNotification $notification): string` returning a relative URL.
- Reads `data.subject_type` + `data.subject_id` + `data.trip_id` + `data.changed_area` from the notification row.
- Maps `subject_type` → panel + anchor using the table from Phase 01.
- Verifies the subject row still exists (lightweight `exists()` query through the morph map). If gone, returns the panel-level URL with `&missing=1`.
- Falls back to the trip overview if `subject_type` is null or not on the map.
- Always appends `from=notification`.
- Pure function over its input — no side effects, no auth. Authorization is the caller's job.

### `app/Http/Controllers/NotificationController.php`

- Add `go(Request $request, string $id)`:
  - Loads the notification scoped to the authenticated user (`findOrFail`).
  - If unread, marks read.
  - Resolves a deep link via `NotificationDeepLinkResolver`.
  - Returns `redirect()->to($url)` (302).
- Extend `serialize()`:
  - Expose `subject_type` (short class basename or the morph alias — pick one and document it in the contract; alias is preferred for stability).
  - Expose `subject_id`.
  - Expose `deep_link` — pre-resolved URL so the bell dropdown can render `<Link :href="...">` without an extra round trip when the user hovers/middle-clicks.

### `routes/web.php` (or wherever notification routes live)

- `Route::get('notifications/{id}/go', [NotificationController::class, 'go'])->middleware(['auth'])->name('notifications.go');`
- Run `npm run build` so Wayfinder regenerates `resources/js/routes/notifications.ts` with the new helper.

### `app/Notifications/TripChangedNotification.php`

- Already stores `subject_type` and `subject_id` — no change needed.
- Confirm the morph alias is in the data payload; if not, add it so the resolver doesn't need to resolve aliases itself.

### `config/morph_map.php` (or wherever the morph map is registered)

- Confirm the eight subject types in the Phase 01 table all have stable morph aliases. Add any missing entries.

### Tests

#### `tests/Unit/Services/NotificationDeepLinkResolverTest.php` (new, Pest)

- One case per subject type — assert the resulting URL.
- Trip-level (null subject) → trip overview URL.
- Unknown subject type → trip overview URL.
- Stale subject (subject row deleted) → panel URL with `&missing=1`.

#### `tests/Feature/NotificationGoControllerTest.php` (new, Pest)

- Authenticated user with their own notification → 302 to the resolved URL, notification is marked read.
- Authenticated user with someone else's notification → 404.
- Already-read notification → 302 to the same URL, no second `read_at` write.
- Unauthenticated → redirect to login.

## State Management

- The resolver is stateless. Notification rows remain the persistent state.
- The new `deep_link` field is a denormalized convenience; the canonical truth is the resolver. If the contract changes, regenerate the field on read — never store it on the notification row.

## Acceptance Criteria

- `GET /notifications/{id}/go` resolves to the correct URL for every subject type listed in the contract.
- Stale-subject and trip-level fallbacks behave per the contract.
- Notification serializer includes `subject_type`, `subject_id`, and `deep_link`.
- Resolver test and controller test pass.
- `php artisan test --compact --filter=NotificationDeepLink` and `--filter=NotificationGoController` run green.
- `npm run build` regenerates `notifications.ts` with the new `go()` helper.

## Out Of Scope

- Frontend consumption of `deep_link` (Phase 03).
- Pulse-highlight on the trip show page (Phase 04).
- Removing the existing `POST /notifications/{id}/read` endpoint — keep it for explicit "mark read without leaving the page" flows.
