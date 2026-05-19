# Phase 05 - Tests And Verification

## Goal

Lock the unblock with comprehensive test coverage and a real-app verification matrix. After this phase ships, "I cannot make another reservation" is a regression that test suite would catch, and the manual matrix proves the user-visible behavior on the actual trip that prompted the report.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 02, 03, 04
- Blocker: none

## Programmatic Coverage

Three test files, each scoped tight enough to run in isolation via `--filter`.

### `tests/Feature/Trips/ReservationCreateTest.php`

Happy paths and validator scope:

- `it creates a flight reservation with the minimum required fields`
- `it creates a lodging reservation with property_name and room_type`
- `it creates a custom-type reservation without flight or lodging sidecars`
- `it requires a title`
- `it requires a starts_timezone and an ends_timezone`
- `it stores starts_at and ends_at as datetimes`
- `it records a TripActivityEvent of type reservation.created`
- `it 403s a viewer-only collaborator`
- `it 404s when the trip belongs to another user with no collaboration link`

### `tests/Feature/Trips/ReservationCreateErrorsTest.php` (started in Phase 02)

Every silent-failure path the audit found:

- `it returns errors.title for missing title`
- `it returns errors.starts_timezone for bad timezone string`
- `it returns errors.starts_timezone for shorthand codes (PST, EST, etc.)`
- `it returns errors.ends_at for ends_at before starts_at (cross-tz aware)`
- `it returns errors.booking_reference for >120 chars`
- `it returns errors.provider_name for >160 chars`
- `it returns errors.notes for >2000 chars`
- `it returns errors.contact_email for invalid email (edit form only — add form omits)`
- `it returns errors.flight_details.currency for non-uppercase but coerces it on save instead`
- `it returns 302 back with all errors in session (regression on Inertia contract)`

### `tests/Browser/ReservationCreateFlowTest.php` (Pest 4 browser)

End-to-end from the user's perspective:

```php
test('user adds a flight reservation end to end', function () {
    $trip = Trip::factory()->forUser($this->user)->create([
        'destination' => 'Manila, Philippines',
        'starts_on' => '2026-09-26',
        'ends_on' => '2026-10-05',
    ]);

    $page = visit(route('trips.show', $trip))
        ->actingAs($this->user)
        ->click('Reservations')
        ->click('Add reservation')
        ->fill('title', 'LAX → MNL')
        ->fill('provider_name', 'United')
        ->select('starts_timezone', 'America/Los_Angeles')
        ->select('ends_timezone', 'Asia/Manila')
        ->fill('starts_at', '2026-09-26T22:00')
        ->fill('ends_at', '2026-09-28T06:00')
        ->fill('airline', 'United')
        ->fill('flight_number', 'UA101')
        ->fill('departure_airport', 'LAX')
        ->fill('arrival_airport', 'MNL')
        ->click('Add reservation');

    $page->assertSee('Reservation added.');
    expect($trip->fresh()->reservations()->count())->toBe(1);
});

test('the form shows an error when the timezone is invalid', function () {
    // Override the picker to allow freeform for the test — or simulate the
    // legacy submit shape — by direct router.post in evaluateJs.
    // Confirm during implementation which path Pest 4 supports.
});

test('the form scrolls to the first invalid field', function () {
    // Submit with missing title, assert window.scrollY changed and title input has focus.
});
```

If the Pest 4 browser DSL can't drive the combobox cleanly, fall back to dispatching the underlying `change` event via `evaluateJs`. Document the choice in the test file's docblock.

## Manual Verification Matrix

Run after Phases 02–04 land. Each row gets a check, a screenshot under `ai/audit/reservation-create-2026-05-18/verification/`, and a one-line note in the state file.

| # | Scenario | Expected |
| --- | --- | --- |
| 1 | Add reservation, title-only, picker tz defaults. | Saves. New row appears. |
| 2 | Add reservation, no title. | Inline error under title input. Summary at top. Toast. Focus moves to title. |
| 3 | Add reservation, picker swapped to `Asia/Manila` for both. | Saves. Reservation card shows Manila times. |
| 4 | Add reservation, `ends_at` before `starts_at`. | Inline error under ends_at. Summary. Toast. |
| 5 | Add reservation, paste 200-char booking_reference. | Inline error under booking_reference. |
| 6 | Add reservation, type 'PST' in a picker search box. | Picker shows `America/Los_Angeles` as top result. Selecting it stores the IANA name. |
| 7 | Add reservation, link toggle on, change start tz. | End tz mirrors automatically. |
| 8 | Sign out in tab B, submit in tab A. | 419 toast: "Your session expired — refresh and try again." |
| 9 | Throttle network to offline, submit. | Toast: "Couldn't save — check your connection." (Or whatever the existing networkError surface says — confirm.) |
| 10 | Add reservation, then edit it. | Edit form opens with picker values populated. Same error treatment. |
| 11 | Add lodging reservation with property_name. | Saves. `LodgingStay` sidecar created. |
| 12 | Add flight reservation with cross-dateline timestamps (LAX→MNL example). | Saves. `FlightSegment` sidecar created with correct timezones. |
| 13 | As a viewer-only collaborator, view the page. | No "Add reservation" form rendered. (Regression check.) |
| 14 | After Phase 02 changes, the packing/cost/task/document/reminder forms still post happy. | All still work. (Regression check.) |
| 15 | After Phase 03 changes, the existing reservations on the trip still display correctly. | No regression on the read path. |

## Acceptance Criteria

- `php artisan test --compact` is green.
- `php artisan test --compact --filter='Reservation'` runs in <30s.
- The Pest 4 browser flow test passes locally and in CI.
- All 15 manual matrix rows are checked off with a screenshot.
- `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent` all green.
- `STATE.md` updated to add this module as `verified`.
- A short release note added to the activity log:

> Adding a reservation now tells you exactly what's wrong when something doesn't save — every field shows its own error, the form scrolls to the first problem, and the timezone fields are pickers instead of free-text. The most common silent failure ("nothing happens when I click Add") is gone.

## Out Of Scope

- A full E2E browser test for every panel on `Trips/Show.vue` (this module covers reservations only).
- Performance benchmarking of the form (the page is already heavy; that's a separate effort).
- Visual regression / screenshot diffing tooling.
- Smoke testing the imports / automation suggestion paths (separate modules).
- Localization of the new error copy.
