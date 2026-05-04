# Phase 03 - Reservations, Dates, And Times

## Status

- Phase id: `phase-03-reservations-dates-and-times`
- Status: `verified`
- Depends on: Phase 02
- Blocks: Phase 04
- Completion gate: Flight, hotel, transport, activity, and dining reservations can be recorded with accurate local dates, times, time zones, confirmation details, and itinerary links.

## Goal

Build the reservation layer for the app. This phase handles the details that matter during travel: flight departures and arrivals, hotel check-in and checkout, rental cars, trains, tours, restaurant bookings, and confirmation numbers.

## Features

- Reservation dashboard inside each trip.
- Reservation types:
  - flights
  - hotels and lodging
  - rental cars
  - trains and buses
  - cruises and ferries
  - activities and tours
  - dining reservations
  - travel insurance
  - custom bookings
- Date and time fields for each reservation type.
- Time-zone-aware storage and display.
- Confirmation number, provider, booking source, support phone, support email, and cancellation policy.
- Link reservations to itinerary items.
- Status tracking: `researching`, `reserved`, `confirmed`, `checked_in`, `cancelled`, `completed`.
- Attachment slots for confirmations and tickets.

## Data Model

### `reservations`

- `id`
- `trip_id`
- `itinerary_item_id`
- `type`
- `title`
- `provider_name`
- `booking_reference`
- `booking_source`
- `status`
- `starts_at`
- `starts_timezone`
- `ends_at`
- `ends_timezone`
- `location_name`
- `address`
- `contact_phone`
- `contact_email`
- `cancellation_policy`
- `notes`
- `raw_details`
- `created_at`
- `updated_at`

### `flight_segments`

- `id`
- `reservation_id`
- `airline`
- `flight_number`
- `confirmation_code`
- `departure_airport`
- `arrival_airport`
- `departs_at`
- `departure_timezone`
- `arrives_at`
- `arrival_timezone`
- `seat`
- `terminal`
- `gate`
- `baggage_claim`
- `segment_order`
- `created_at`
- `updated_at`

### `lodging_stays`

- `id`
- `reservation_id`
- `property_name`
- `room_type`
- `check_in_at`
- `check_in_timezone`
- `check_out_at`
- `check_out_timezone`
- `address`
- `phone`
- `late_arrival_note`
- `parking_details`
- `created_at`
- `updated_at`

## Backend Work

- Add reservation base model and specialized detail tables where needed.
- Add validation for date/time/time-zone combinations.
- Add services to sync reservation records into itinerary items.
- Add query scopes for upcoming, current, and completed reservations.
- Add file attachment hooks that Phase 04 can expand.

## Frontend Work

- Add reservation list, detail, create, and edit views.
- Build type-specific forms for flights and lodging first.
- Build generic reservation form for other types.
- Display local time clearly, including arrival dates when a flight crosses midnight.
- Show confirmation details in a scannable travel-day layout.

## Tests

- Feature tests for each major reservation type.
- Tests for flight segments across different time zones.
- Tests for linking and unlinking itinerary items.
- Tests for reservation status transitions.
- Validation tests for invalid time ranges.

## Acceptance Criteria

- A user can enter a flight with departure and arrival airports, local times, and time zones.
- A user can enter hotel check-in and checkout details.
- Reservation details are visible from both the reservations area and linked itinerary day.
- Crossing time zones and midnight boundaries display correctly.
- Tests pass for the implemented scope.

## Notes For Later Phases

- Do not build live status polling yet. Keep enough fields to update manually and support automated integrations in Phase 06.
