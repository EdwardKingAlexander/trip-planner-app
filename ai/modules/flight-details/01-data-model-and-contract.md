# Phase 01 - Data Model And Contract

## Goal

Lock the migration shape, the model, the validation rules, and the create-or-empty decision so Phases 02–04 can build against a frozen agreement.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: none
- Blocker: none

## Audit Inputs

- `database/migrations/2026_05_04_000001_create_trip_management_tables.php:122-138` — `lodging_stays` table is the structural template (one-to-one sidecar on `reservations` with `cascadeOnDelete`).
- `app/Models/LodgingStay.php` (assumed; same shape as `FlightSegment.php:1-40`) — model template for the new `FlightDetails` model.
- `app/Models/Reservation.php:56-64` — already exposes `flightSegments()` and `lodgingStay()`. Needs a new `flightDetails()` HasOne relation.
- `app/Http/Controllers/TripReservationController.php:82-105` — current `validatedReservation()`. Will gain a single nested `flight_details` array.
- `app/Http/Controllers/TripReservationController.php:120-163` — current `syncReservationDetails()`. Phase 02 extends this; the contract here freezes its inputs.
- `app/Models/UserTravelPreference.php:10-16` — `default_currency` column already exists on the per-user travel preference row. Used as the default-currency source.
- `app/Http/Controllers/TripPlanningController.php:366` — current `currency` validation pattern (`['required','string','size:3']`); reused for `flight_details.currency` (but **nullable** there).

## Migration (frozen)

New migration file: `database/migrations/<TIMESTAMP>_create_flight_details_table.php`.

```php
Schema::create('flight_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();

    $table->string('cabin_class', 40)->nullable();
    $table->string('currency', 3)->nullable();

    $table->string('carry_on_size', 120)->nullable();
    $table->string('carry_on_weight', 40)->nullable();
    $table->decimal('carry_on_fee', 10, 2)->nullable();

    $table->string('personal_item_size', 120)->nullable();
    $table->string('personal_item_weight', 40)->nullable();
    $table->decimal('personal_item_fee', 10, 2)->nullable();

    $table->string('checked_bag_size', 120)->nullable();
    $table->string('checked_bag_weight', 40)->nullable();
    $table->decimal('checked_bag_fee', 10, 2)->nullable();

    $table->decimal('additional_checked_bag_fee', 10, 2)->nullable();
    $table->string('additional_checked_bag_allowance', 80)->nullable();

    $table->text('visa_requirement')->nullable();
    $table->text('passport_validity_rule')->nullable();

    $table->text('layover_notes')->nullable();
    $table->string('online_check_in_opens', 80)->nullable();
    $table->string('boarding_closes', 80)->nullable();

    $table->text('notes')->nullable();

    $table->timestamps();
});
```

`down()` drops the table. No backfill — existing reservations have no row; the serializer treats missing as "unfilled".

`reservation_id` is `unique()`, enforcing the one-to-one relation at the DB level.

## Model (frozen)

New file `app/Models/FlightDetails.php`:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightDetails extends Model
{
    protected $table = 'flight_details';

    protected $fillable = [
        'reservation_id',
        'cabin_class',
        'currency',
        'carry_on_size',
        'carry_on_weight',
        'carry_on_fee',
        'personal_item_size',
        'personal_item_weight',
        'personal_item_fee',
        'checked_bag_size',
        'checked_bag_weight',
        'checked_bag_fee',
        'additional_checked_bag_fee',
        'additional_checked_bag_allowance',
        'visa_requirement',
        'passport_validity_rule',
        'layover_notes',
        'online_check_in_opens',
        'boarding_closes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'carry_on_fee' => 'decimal:2',
            'personal_item_fee' => 'decimal:2',
            'checked_bag_fee' => 'decimal:2',
            'additional_checked_bag_fee' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
```

Update `app/Models/Reservation.php` with one new relation:

```php
public function flightDetails(): HasOne
{
    return $this->hasOne(FlightDetails::class);
}
```

`TracksAuthor` is **not** added — the parent reservation already records authorship and there is no per-field history requirement here.

## Cabin Class Enum (frozen)

Allowed values:

```
economy
premium_economy
business
first
```

Stored as a string for forward compatibility (no DB enum). Validated via Laravel's `Rule::in([...])`.

Frontend label map (used in Phase 03 / 04):

| Stored value | Display label |
| --- | --- |
| `economy` | Economy |
| `premium_economy` | Premium Economy |
| `business` | Business |
| `first` | First |

## Currency Default Cascade (frozen)

When the edit form is opened on a flight that has no `flight_details.currency` set, the form pre-fills the dropdown using this cascade:

1. `UserTravelPreference.default_currency` for the current user, if non-null.
2. The most recent `trip_costs.currency` on this trip (latest by `id`), if any cost rows exist.
3. Hardcoded `USD`.

The cascade is computed server-side in the trip serializer and passed down as `trip.suggested_currency`. The frontend uses it only as a default — once the user picks anything, that selection is what gets saved.

The existing currency dropdown in the budget panel (already in `Trips/Show.vue`) is the pattern to follow for the picker. No new component needed.

## Lifecycle Decision (frozen)

**Lazy creation.** A `flight_details` row is **not** auto-created when a flight reservation is added. The row is created the first time the user submits the form with at least one non-null value. If the user clears every field back to null, the row is deleted (so the read-only summary cleanly hides).

Why lazy:

- Keeps the table empty for users who never use the feature.
- Matches the cleanest "nothing to show" semantics for the read-only card.
- Mirrors `TripDocument`'s pattern: data only exists when there is data.

Implementation lives in Phase 02's extended `syncReservationDetails()`.

## Validation Rules (frozen)

`TripReservationController::validatedReservation()` gains one new top-level field, a nested array:

```php
'flight_details' => ['nullable', 'array'],
'flight_details.cabin_class' => ['nullable', 'string', Rule::in(['economy', 'premium_economy', 'business', 'first'])],
'flight_details.currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],

'flight_details.carry_on_size' => ['nullable', 'string', 'max:120'],
'flight_details.carry_on_weight' => ['nullable', 'string', 'max:40'],
'flight_details.carry_on_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],

'flight_details.personal_item_size' => ['nullable', 'string', 'max:120'],
'flight_details.personal_item_weight' => ['nullable', 'string', 'max:40'],
'flight_details.personal_item_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],

'flight_details.checked_bag_size' => ['nullable', 'string', 'max:120'],
'flight_details.checked_bag_weight' => ['nullable', 'string', 'max:40'],
'flight_details.checked_bag_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],

'flight_details.additional_checked_bag_fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
'flight_details.additional_checked_bag_allowance' => ['nullable', 'string', 'max:80'],

'flight_details.visa_requirement' => ['nullable', 'string', 'max:1000'],
'flight_details.passport_validity_rule' => ['nullable', 'string', 'max:1000'],

'flight_details.layover_notes' => ['nullable', 'string', 'max:1000'],
'flight_details.online_check_in_opens' => ['nullable', 'string', 'max:80'],
'flight_details.boarding_closes' => ['nullable', 'string', 'max:80'],

'flight_details.notes' => ['nullable', 'string', 'max:1000'],
```

Notes:

- Currency uses both `size:3` (length) and a regex (uppercase letters only) — keeps the front-end free to render any 3-letter ISO 4217 code without a hardcoded list.
- Top-level `flight_details` must be an array (never a string), so a misplaced field doesn't silently fail.
- All fee fields cap at `99999.99` — no airline charges five figures for a single bag, but the bound prevents accidental nonsense (and forces decimal-place validation to fire on input like `100000.00`).
- All fields are `nullable`. The form serializing logic (Phase 03) sends explicit `null` for fields the user clears, so the controller can drop them.

## Trip Serializer Additions (frozen)

`TripController::tripDetail()` `'reservations'` array entries gain one new key, and the controller eager loads one new relation.

Eager load (line ~73 alongside existing reservation loads):

```php
'reservations.flightDetails',
```

Mapping (line ~225):

```php
'reservations' => $trip->reservations->map(fn ($reservation) => [
    // ...existing fields...
    'flight_segments' => $reservation->flightSegments,
    'lodging_stay' => $reservation->lodgingStay,
    'flight_details' => $reservation->flightDetails,  // null when no row exists
    'document_count' => $trip->documents->where('reservation_id', $reservation->id)->count(),
    'last_edited_by' => $this->editorName($reservation),
]),
```

The trip payload also gains a top-level `suggested_currency` key:

```php
'trip' => array_merge(
    $this->tripSummary($trip, $userId),
    [
        // ...existing keys...
        'suggested_currency' => $this->suggestedCurrencyFor($trip, $request->user()),
    ],
)
```

```php
private function suggestedCurrencyFor(Trip $trip, ?User $user): string
{
    $userPreference = $user?->travelPreference?->default_currency;
    if ($userPreference) {
        return $userPreference;
    }

    $latestCostCurrency = $trip->costs()->latest('id')->value('currency');
    if ($latestCostCurrency) {
        return $latestCostCurrency;
    }

    return 'USD';
}
```

(If `User::travelPreference()` doesn't exist as a relation, add it as `hasOne(UserTravelPreference::class)` in this phase. Confirm before Phase 02.)

## Frontend Type Additions

`resources/js/pages/Trips/Show.vue` reservation type gains:

```ts
type FlightDetails = {
    id: number;
    reservation_id: number;
    cabin_class: 'economy' | 'premium_economy' | 'business' | 'first' | null;
    currency: string | null;
    carry_on_size: string | null;
    carry_on_weight: string | null;
    carry_on_fee: string | null;
    personal_item_size: string | null;
    personal_item_weight: string | null;
    personal_item_fee: string | null;
    checked_bag_size: string | null;
    checked_bag_weight: string | null;
    checked_bag_fee: string | null;
    additional_checked_bag_fee: string | null;
    additional_checked_bag_allowance: string | null;
    visa_requirement: string | null;
    passport_validity_rule: string | null;
    layover_notes: string | null;
    online_check_in_opens: string | null;
    boarding_closes: string | null;
    notes: string | null;
};

type Reservation = Record<string, any> & {
    // ...existing fields...
    flight_details: FlightDetails | null;
    document_count: number;
};

// On the Trip type:
suggested_currency: string;
```

Decimal fields come back as strings from Laravel's `decimal:2` cast — typed as `string | null` accordingly.

## Open Questions To Resolve Before Phase 02

- Confirm "lazy creation" semantics: row only exists when at least one field is non-null; clearing all fields deletes the row.
- Confirm the four-option cabin enum vs adding `economy_basic` (basic economy) as a fifth.
- Confirm `User::travelPreference()` relation exists; if not, add it as part of Phase 01.
- Confirm migration timestamp slot.
- Confirm `personal_item_*` is the right canonical label for the user's "extra baggage" wording (vs `extra_bag_*`).

Record answers in `ai/state/flight-details.json` `decisions[]` before Phase 02.

## State Management

- Server is the source of truth for everything in `flight_details`.
- Currency suggestion is computed server-side and passed down per visit — never cached client-side.
- The frontend's edit form holds form state in Inertia `useForm` for the duration of the edit; on save it does a full Inertia visit and the page re-renders against the fresh trip payload.
- No optimistic state — the edit form is a discrete "open / fill / save" interaction, not an inline toggle.

## Acceptance Criteria

- Migration is written and runs cleanly forward and backward against a fresh DB.
- `FlightDetails` model exists with the right fillable / casts / relation.
- `Reservation::flightDetails()` HasOne relation is defined and eager-loadable.
- `validatedReservation()` accepts and validates the nested `flight_details` array.
- Trip serializer includes `flight_details` per reservation and `suggested_currency` on the trip.
- Frontend `FlightDetails` and updated `Reservation` types are extended in `Trips/Show.vue`.
- Open questions are answered in the state JSON.

## Out Of Scope

- Building the controller sync logic (Phase 02).
- Building the edit form UI (Phase 03).
- Building the read-only summary (Phase 04).
- Per-segment overrides.
- Currency conversion or auto-summing into the budget panel.
- Frequent flyer / meal / loyalty fields.
