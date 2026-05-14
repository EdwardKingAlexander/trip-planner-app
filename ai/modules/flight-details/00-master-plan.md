# Flight Details Master Plan

## Goal

Give every flight reservation an optional, structured "flight details" sidecar covering baggage allowance + fees per bag type, cabin class, prices in a chosen currency, the international gotchas (visa / passport rules), and a free-text notes block. Keep it nullable — short domestic hops shouldn't be forced to fill anything; long-haul international bookings get a single screen that holds everything that matters before boarding.

## Status

- Status: `planned`
- State file: `ai/state/flight-details.json`
- Last updated: 2026-05-13

## Problem

Today the flight reservation captures airline, flight number, departure/arrival airports, and the day-of trio of seat / terminal / gate (`app/Models/FlightSegment.php:10-26`). Nothing exists for:

- Baggage allowance (carry-on size, personal item, checked bag size / weight) or the fees the airline charges for each / for additional bags.
- Cabin class — directly drives baggage allowance and amenities, but unrecorded.
- Visa requirements or passport validity rules per flight — the most common international "I forgot" item.
- Per-flight price annotations beyond the existing `trip_costs` records.

The reservation form (`app/Http/Controllers/TripReservationController.php:82-105`) only validates the basics. The reservation card (`resources/js/pages/Trips/Show.vue:889-925`) shows title / provider / booking ref / dates / address / notes / status pill. International travelers end up keeping baggage screenshots in their phone gallery and writing visa rules into the generic `notes` field, where they get buried.

## Strategy

Five threads, each independently shippable:

1. **Sidecar table, not column-on-reservation.** Add a `flight_details` table that is one-to-one with `reservations` where `type = 'flight'`, mirroring the existing `lodging_stays` pattern (`database/migrations/2026_05_04_000001_create_trip_management_tables.php:122-138`). Keeps the wider `reservations` table clean and gives every flight a single home for nullable extras.
2. **Validated controller plumbing** — `TripReservationController` already runs a `syncReservationDetails()` step inside its store/update transaction (`app/Http/Controllers/TripReservationController.php:120-163`). Extend it to handle a `flight_details` payload using the same `updateOrCreate` pattern. Validation accepts every field as nullable so the existing flight create flow keeps working without payload changes.
3. **Two-pane edit form** — the existing reservation edit form gains a collapsible "Flight details" section (collapsed by default, auto-expanded when any field has a value). Sections inside: Cabin & pricing, Baggage allowance (3 bag types × size / weight / fee + an extra checked-bag fee field), Travel docs (visa rule + passport rule), and Free-text notes.
4. **Read-only summary on the reservation card** — when the user is not editing, the card surfaces a compact baggage-and-cabin summary line ("Economy · Carry-on 55×40×20 cm 7 kg · 1 checked bag included") plus a "Show full details" toggle that reveals the rest. Designed to disappear cleanly when no details are filled.
5. **Trip-default currency, overridable per flight.** The `flight_details.currency` defaults to the trip's primary currency on create (or `USD` if the trip has no primary). Single dropdown lives next to the price fields so the user can override per flight without re-typing on every booking.

State management mirrors existing patterns: server is the source of truth, the edit form uses the existing `useForm`-driven submission flow (no optimistic updates — full Inertia visit), the read-only card derives every label from the trip serializer payload.

## Phases

1. [Data Model And Contract](01-data-model-and-contract.md)
2. [Backend Controller And Serializer](02-backend-controller-and-serializer.md)
3. [Frontend Flight Details Form](03-frontend-flight-details-form.md)
4. [Frontend Flight Details Read-Only Display](04-frontend-flight-details-readonly.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Phase 01 freezes the migration shape, validation rules, and sidecar contract so everything else builds against a stable agreement. Phase 02 ships the controller change + serializer additions with focused tests. Phase 03 wires the edit form (the primary input surface). Phase 04 adds the read-only summary line and the expandable details panel on the reservation card. Phase 05 is the gate.

## Field Inventory (frozen)

The `flight_details` row holds exactly these columns. Every field is nullable.

| Field | Type | Notes |
| --- | --- | --- |
| `reservation_id` | `foreignId` (unique) | One-to-one with reservation. `cascadeOnDelete`. |
| `cabin_class` | `string(40)` | Enum-validated: `economy`, `premium_economy`, `business`, `first`. |
| `currency` | `string(3)` | ISO 4217. Defaults to trip primary currency at create time. |
| `carry_on_size` | `string(120)` | Free-text size, e.g. `55×40×20 cm`. |
| `carry_on_weight` | `string(40)` | Free-text weight, e.g. `7 kg` or `15 lb`. |
| `carry_on_fee` | `decimal(10,2)` | Fee for carry-on if not included. |
| `personal_item_size` | `string(120)` | Free-text. Labelled "Personal item / extra carry" in the UI. |
| `personal_item_weight` | `string(40)` | Free-text. |
| `personal_item_fee` | `decimal(10,2)` | Free-text. |
| `checked_bag_size` | `string(120)` | Free-text. |
| `checked_bag_weight` | `string(40)` | Free-text. |
| `checked_bag_fee` | `decimal(10,2)` | Fee for the first/included checked bag. |
| `additional_checked_bag_fee` | `decimal(10,2)` | Per-bag fee for buying more checked bags beyond the first. |
| `additional_checked_bag_allowance` | `string(80)` | Free-text, e.g. `Up to 3 extra @ this fee`. |
| `visa_requirement` | `text` | Free-text, e.g. `eTA Canada — apply 7 days ahead`. |
| `passport_validity_rule` | `text` | Free-text, e.g. `Must be valid through 2027-01-15`. |
| `layover_notes` | `text` | Free-text, e.g. `Terminal change at LHR T2→T5; minimum connection 90 min; separate ticket — re-check baggage`. |
| `online_check_in_opens` | `string(80)` | Free-text, e.g. `24h before departure` or `2026-06-12 09:00 EDT`. |
| `boarding_closes` | `string(80)` | Free-text, e.g. `30 min before departure` or `Gate closes 19:45`. |
| `notes` | `text` | Free-text, dedicated to flight details (separate from `reservations.notes`). |
| `created_at` / `updated_at` | timestamps |  |

`TracksAuthor` is **not** added in this module. The parent `Reservation` already records `created_by_user_id` and `updated_by_user_id`; the sidecar inherits attribution through that.

## Acceptance Criteria

- A signed-in user with `trip.update` can fill any subset of flight detail fields on an existing flight reservation; all fields are nullable, and short domestic flights can leave them empty without warnings.
- Creating a flight reservation does not require any flight-details fields. The sidecar row is created lazily on first save with values, OR is always created empty when the reservation is created (decision locked in Phase 01).
- Switching a reservation away from `type = 'flight'` deletes the sidecar (matches existing behavior for `flight_segments` and `lodging_stays` in `syncReservationDetails`).
- The cabin class is validated against the four-option enum on the server.
- The currency is validated as ISO 4217 (3 letters); the form defaults to the trip's primary currency on first edit.
- Baggage size/weight fields accept any short free-text the user types — no unit conversion, no validation beyond max length.
- Price fields accept positive decimals up to 2 places; empty / null is allowed.
- Visa / passport / notes fields accept up to 1000 characters each.
- The reservation card shows a one-line summary when at least one detail is filled (e.g. "Economy · Carry-on 55×40×20 cm, 7 kg · Checked $30") and exposes a "Show full details" toggle that reveals the rest.
- The reservation card shows nothing extra when every field is null — no empty section, no placeholder text.
- Activity events fire as `reservation.updated` (no new event type — flight details are part of the umbrella reservation update).
- All standard checks pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`, `php artisan wayfinder:generate --with-form --no-interaction`.

## Out Of Scope

- Per-segment overrides on a multi-leg ticket (codeshare differences). One details record per reservation; per-segment can ship later as a separate module.
- Per-price currency. One currency per flight; mixed-currency bookings live in `notes`.
- Frequent flyer / loyalty number + tier (deferred — explicitly not selected).
- Meal preference field (deferred — explicitly not selected; sits naturally in `notes` for now).
- Auto-import of baggage rules from the airline (would need a per-airline knowledge base).
- Currency conversion or summing across flights into the `trip_costs` panel.
- Mobile boarding-pass viewer (covered by `document-uploads`).
- Reminders for the meal-request 24h window (could ride on top of `trip_reminders` later).
- Bag-type templates ("set my usual" presets) — useful but a follow-up.
- Airline lookup / autocomplete for cabin class names.
