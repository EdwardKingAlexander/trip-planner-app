# Phase 02 - Backend Toggle Endpoint

## Goal

Ship the focused `PATCH .../packed` endpoint with full test coverage and the new activity event type, so the frontend has a stable target.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01 (contract)
- Blocker: none

## Planned Changes

### `routes/web.php` (or the existing trip route file)

- Add:
  ```php
  Route::patch('trips/{trip}/packing-items/{packingItem}/packed', [TripPlanningController::class, 'togglePacked'])
      ->middleware(['auth'])
      ->name('trips.packing-items.toggle-packed');
  ```
- Run `npm run build` so Wayfinder regenerates `resources/js/routes/trips/packing-items.ts` with a `togglePacked` (or `packed`) helper.

### `app/Http/Controllers/TripPlanningController.php`

- Add `togglePacked(Request $request, Trip $trip, PackingItem $packingItem, TripCollaborationEventService $events): RedirectResponse`:
  - `$this->authorize('update', $trip)`.
  - `abort_unless($packingItem->trip_id === $trip->id, 404)`.
  - Validate `['is_packed' => 'required|boolean']`.
  - `$packingItem->update(['is_packed' => $validated['is_packed']])`.
  - Record an activity event:
    ```php
    $events->record(
        trip: $trip,
        eventType: 'packing.toggled',
        changedArea: 'packing',
        summary: ($validated['is_packed'] ? 'marked packed: ' : 'marked unpacked: ') . $packingItem->label,
        subject: $packingItem,
    );
    ```
  - `return back()->with('success', $validated['is_packed'] ? 'Marked packed.' : 'Marked unpacked.');`.

### Optional: tracker on `TripCollaborationEventService`

- The `packing.toggled` event type is new. If the service has an explicit allowlist, add `packing.toggled` to it. If it accepts arbitrary types, no change.

### Tests

#### `tests/Feature/Trip/PackingTogglePackedTest.php` (new, Pest)

- `it('toggles packed state for the trip owner')` — patch with `is_packed: true`, assert DB row updated, assert activity event recorded with the right summary.
- `it('toggles back to unpacked')` — patch with `is_packed: false`, repeat the assertions.
- `it('records the actor as last_edited_by')` — confirms the existing `TracksAuthor` concern still fires.
- `it('rejects a non-boolean value')` — 422.
- `it('rejects when the user is not on the trip')` — 403.
- `it('returns 404 when the packing item does not belong to the trip')` — using IDs from a different trip.
- `it('requires auth')` — redirect to login.

#### Existing `updatePacking` tests

- Confirm none of them break. The two endpoints coexist.

## State Management

- DB `packing_items.is_packed` is the source of truth.
- Activity events table accumulates `packing.toggled` rows. No new tables.
- No new caches.

## Acceptance Criteria

- `PATCH /trips/{trip}/packing-items/{packingItem}/packed` returns 302 on success and 422 / 403 / 404 / 401 on the appropriate failures.
- The new test file passes; the existing trip planning tests still pass.
- `php artisan route:list --name=trips.packing-items` shows the new entry.
- `npm run build` regenerates the typed Wayfinder helper.
- `vendor/bin/pint --dirty --format agent` reports clean.

## Out Of Scope

- Frontend consumption (Phase 03).
- Bulk endpoints.
- Soft-undo flow beyond the optimistic frontend rollback.
