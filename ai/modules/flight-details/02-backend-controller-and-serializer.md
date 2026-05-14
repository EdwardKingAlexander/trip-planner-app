# Phase 02 - Backend Controller And Serializer

## Goal

Wire the new `flight_details` sidecar into the existing reservation create/update flow with the lazy-create semantics frozen in Phase 01, eager-load it into the trip serializer, and ship a focused test suite that proves nullable behavior, type-switching cleanup, and the currency-default cascade.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phase 01 (migration + model + validation rules landed)
- Blocker: none

## Audit Inputs

- `app/Http/Controllers/TripReservationController.php:14-37` — `store()` runs `validatedReservation()` then a `DB::transaction` that calls `syncReservationDetails()` after the reservation is created. Same pattern in `update()` at lines 39-61.
- `app/Http/Controllers/TripReservationController.php:120-163` — current `syncReservationDetails()` handles flight segments and lodging stay. Phase 02 extends it to handle `flight_details`.
- `app/Http/Controllers/TripReservationController.php:108-118` — current `reservationAttributes()` strips out flight/lodging-specific fields before saving the parent reservation. The new `flight_details` array is also stripped here.
- `app/Http/Controllers/TripController.php:73,225` — current eager loads + serializer for reservations. Two new lines (eager load + payload key).
- `app/Services/TripCollaborationEventService.php:22-83` — `record()` is unchanged; flight details edits ride on `reservation.updated`.

## Planned Changes

### `app/Http/Controllers/TripReservationController.php`

#### `validatedReservation()`

Append the rules from Phase 01 (the `flight_details.*` block). Result list stays sorted by section: top-level reservation rules, then `flight_details.*` rules in the order they appear in the migration.

#### `reservationAttributes()`

Add `'flight_details'` to the strip-list:

```php
return collect($validated)->except([
    'airline',
    'flight_number',
    'departure_airport',
    'arrival_airport',
    'property_name',
    'room_type',
    'flight_details',
])->all();
```

#### `syncReservationDetails()`

After the existing flight/lodging blocks, add:

```php
$flightDetails = $validated['flight_details'] ?? null;
$hasAnyValue = $flightDetails && collect($flightDetails)->filter(
    fn ($v) => $v !== null && $v !== '' && $v !== []
)->isNotEmpty();

if ($reservation->type === 'flight' && $hasAnyValue) {
    $reservation->flightDetails()->updateOrCreate(
        [],  // unique reservation_id is enforced; updateOrCreate matches the single row
        $this->cleanFlightDetailsPayload($flightDetails),
    );
} else {
    // type changed away from flight, OR every field was cleared:
    $reservation->flightDetails()->delete();
}
```

```php
private function cleanFlightDetailsPayload(array $payload): array
{
    return [
        'cabin_class'                       => $payload['cabin_class'] ?? null,
        'currency'                          => $payload['currency'] ?? null,
        'carry_on_size'                     => $payload['carry_on_size'] ?? null,
        'carry_on_weight'                   => $payload['carry_on_weight'] ?? null,
        'carry_on_fee'                      => $payload['carry_on_fee'] ?? null,
        'personal_item_size'                => $payload['personal_item_size'] ?? null,
        'personal_item_weight'              => $payload['personal_item_weight'] ?? null,
        'personal_item_fee'                 => $payload['personal_item_fee'] ?? null,
        'checked_bag_size'                  => $payload['checked_bag_size'] ?? null,
        'checked_bag_weight'                => $payload['checked_bag_weight'] ?? null,
        'checked_bag_fee'                   => $payload['checked_bag_fee'] ?? null,
        'additional_checked_bag_fee'        => $payload['additional_checked_bag_fee'] ?? null,
        'additional_checked_bag_allowance'  => $payload['additional_checked_bag_allowance'] ?? null,
        'visa_requirement'                  => $payload['visa_requirement'] ?? null,
        'passport_validity_rule'            => $payload['passport_validity_rule'] ?? null,
        'layover_notes'                     => $payload['layover_notes'] ?? null,
        'online_check_in_opens'             => $payload['online_check_in_opens'] ?? null,
        'boarding_closes'                   => $payload['boarding_closes'] ?? null,
        'notes'                             => $payload['notes'] ?? null,
    ];
}
```

The "cleaning" function exists so future fields require a single edit point and so the controller method itself stays short.

`hasAnyValue` enforces the lazy-create rule from Phase 01: if every value is null / empty string / empty array, the row is deleted (or never created) and the read-only card shows nothing.

### `app/Models/User.php`

If `travelPreference()` does not exist as a relation already (confirm during the open-question pass), add it:

```php
public function travelPreference(): HasOne
{
    return $this->hasOne(UserTravelPreference::class);
}
```

Used by the suggested-currency cascade.

### `app/Http/Controllers/TripController.php`

Three changes:

1. Eager load (line ~73 area):

```php
'reservations.flightDetails',
```

2. Reservation serializer mapping (line ~225) gains:

```php
'flight_details' => $reservation->flightDetails,
```

3. Top-level trip payload gains:

```php
'suggested_currency' => $this->suggestedCurrencyFor($trip, $request->user()),
```

Plus the new private helper from Phase 01.

### `app/Services/TripExportService.php`

The JSON export already pulls `reservations.flightSegments` and `reservations.lodgingStay`. Add the new relation to the eager load and include it in the payload so the export round-trips cleanly:

```php
$trip->load([
    // ...existing...
    'reservations.flightSegments',
    'reservations.lodgingStay',
    'reservations.flightDetails',
    // ...existing...
]);
```

If the export `reservations` payload is built via `toArray()` plus selective merging, the new relation will already be present once eager-loaded. Verify and patch as needed.

## Tests

`tests/Feature/FlightDetailsTest.php` (new, Pest):

### Lazy-create

- `it('does not create a flight_details row when the reservation has no detail values')` — store a flight reservation with `flight_details: { all: null }`, assert no row in `flight_details`.
- `it('creates a flight_details row on first save with any non-null value')` — store with `cabin_class: economy`, assert row exists with that value and the other fields null.
- `it('updates an existing flight_details row in place on subsequent saves')` — save once with `cabin_class`, save again with `carry_on_size`, assert one row total with both fields populated.
- `it('deletes the flight_details row when every field is cleared back to null')` — populate, then save with all fields explicitly null, assert row gone.

### Type switching

- `it('deletes flight_details when the reservation type changes from flight to lodging')` — confirms `syncReservationDetails` cleanup matches the existing `flight_segments` behavior.
- `it('does not create flight_details when the reservation type is not flight')` — even if `flight_details` payload is present, non-flight reservations ignore it.

### Validation

- `it('rejects an invalid cabin_class')` — 422 with field-level error on `flight_details.cabin_class`.
- `it('rejects a non-3-letter currency')` — 422.
- `it('rejects a non-uppercase currency')` — 422.
- `it('rejects a negative fee')` — 422 on `flight_details.carry_on_fee`.
- `it('rejects a fee with more than 2 decimal places')` — 422.
- `it('rejects oversized free-text fields')` — 1001-char visa_requirement returns 422; same for layover_notes and notes.
- `it('rejects oversized check-in window fields')` — 81-char online_check_in_opens returns 422; same for boarding_closes.
- `it('accepts a fully populated flight_details payload')` — the happy path; assert all values match including layover_notes / online_check_in_opens / boarding_closes.

### Currency suggestion

- `it('suggests the user travel-preference currency when present')` — set `UserTravelPreference.default_currency = 'GBP'`, visit trip show, assert `trip.suggested_currency = 'GBP'`.
- `it('suggests the most recent trip cost currency when no user preference exists')` — no user preference, two costs (USD then EUR), assert `trip.suggested_currency = 'EUR'`.
- `it('falls back to USD when no signal is available')` — no preference, no costs, assert `trip.suggested_currency = 'USD'`.

### Authorization

- `it('rejects flight_details edits from a non-editor collaborator')` — viewer-role: 403.
- `it('rejects when the reservation belongs to a different trip')` — 404.
- `it('requires auth')` — 302 to login.

### Trip export

- `it('includes flight_details in the JSON export')` — populate, hit the export route, assert payload contains the row.

Existing `TripManagementTest` should remain green — the new `flight_details` payload is nullable and absent in existing fixtures.

## State Management

- DB: `flight_details` is the only authoritative store.
- Currency suggestion is recomputed on every trip-show visit — no cache.
- Activity events: `reservation.updated` (existing event type) covers flight-details edits — no new type.
- No queues, no caches.

## Acceptance Criteria

- `validatedReservation()` accepts the nested `flight_details` array and validates it per Phase 01.
- `syncReservationDetails()` creates / updates / deletes the sidecar row per the lazy semantics.
- `reservationAttributes()` no longer leaks `flight_details` into the parent reservation update.
- Trip serializer includes `flight_details` per reservation and `suggested_currency` on the trip.
- `TripExportService` round-trips the new payload.
- All assertions in `FlightDetailsTest` pass.
- Existing tests still pass.
- `php artisan test --compact` is green.
- `php artisan wayfinder:generate --with-form --no-interaction` regenerates cleanly (the form helper for `trips.reservations.update` now expects the wider payload).
- `vendor/bin/pint --dirty --format agent` is clean.

## Out Of Scope

- Frontend consumption (Phase 03 / 04).
- Per-segment overrides.
- Migration of existing data — there is no source data to migrate.
- Currency conversion across costs and flight fees.
- Background jobs to refresh suggested currency.
