# Phase 01 - Data Model And Attribution Contract

## Goal

Lock the migration shape, the trip serializer additions, the validation rules, and the assignee endpoint contract so Phases 02–04 build against a frozen agreement.

## Status

- Status: `verified`
- Owner: codex
- Depends on: none
- Blocker: none

## Audit Inputs

- `database/migrations/2026_05_04_000001_create_trip_management_tables.php:152-163` — current `packing_items` schema. `traveler_name` is a nullable string; no FK to a user.
- `database/migrations/2026_05_06_015922_add_author_tracking_to_shared_trip_components.php` — pattern for adding nullable user FKs (`->nullable()->constrained('users')->nullOnDelete()`) on the same set of tables.
- `app/Models/PackingItem.php:13` — current `$fillable` excludes any user FK.
- `app/Concerns/TracksAuthor.php:11-49` — already provides `createdBy()` / `updatedBy()` relations.
- `app/Http/Controllers/TripController.php:75,229-232` — eager loads `packingItems.updatedBy` and only attaches `last_edited_by` when serializing.
- `app/Http/Controllers/TripPlanningController.php:71-104,321-331` — current packing create/update/validate flow. `validatedPacking()` permits `traveler_name` only.
- `resources/js/pages/Trips/Show.vue:80,157-163` — frontend `PackingItem` type and `packingForm`. No assignee field.

## Migration (frozen)

New migration file: `database/migrations/<TIMESTAMP>_add_assigned_user_to_packing_items.php`.

```php
Schema::table('packing_items', function (Blueprint $table) {
    $table->foreignId('assigned_to_user_id')
        ->nullable()
        ->after('traveler_name')
        ->constrained('users')
        ->nullOnDelete();
});
```

`down()` drops the constrained foreign id. No data backfill — existing rows simply have `assigned_to_user_id = null` and continue to render via the `traveler_name` fallback.

## Model (frozen)

`app/Models/PackingItem.php`:

- Add `'assigned_to_user_id'` to `$fillable`.
- Add a relation:
  ```php
  public function assignedTo(): BelongsTo
  {
      return $this->belongsTo(User::class, 'assigned_to_user_id');
  }
  ```
- No cast changes. `created_by_user_id` / `updated_by_user_id` continue to be handled by `TracksAuthor`.

## Validation Rules (frozen)

`TripPlanningController::validatedPacking()` accepts a new optional field. Both create (`packing`) and update (`updatePacking`) use the same rules:

```php
'assigned_to_user_id' => [
    'nullable',
    'integer',
    Rule::exists('users', 'id')->where(function ($query) use ($trip) {
        // Must be the trip owner OR an accepted collaborator with a linked user_id.
        $query->where(function ($q) use ($trip) {
            $q->where('id', $trip->user_id)
              ->orWhereIn('id', $trip->collaborators()
                  ->whereNotNull('user_id')
                  ->pluck('user_id'));
        });
    }),
],
```

The closure-scoped `Rule::exists` keeps the controller from accepting an arbitrary user id (e.g. someone outside the trip). The frontend always sends a vetted id, but server-side enforcement is required.

The frozen validation contract:

| Field | Rule |
| --- | --- |
| `assigned_to_user_id` | `nullable\|integer\|in:<trip-participant-user-ids>` |
| `traveler_name` | unchanged (`nullable\|string\|max:120`) |

Both fields may be set together, or independently. If `assigned_to_user_id` is set, `traveler_name` may still be filled — the frontend hides the free-text field by default when an account is selected, but the column keeps any historical value.

## Trip Serializer Additions (frozen)

`TripController::tripDetail()` `'packing_items'` array entries gain three new keys, and the controller eager loads two new relations.

Eager load (line ~75):

```php
'packingItems.updatedBy',
'packingItems.createdBy',
'packingItems.assignedTo',
```

Mapping (line ~229):

```php
'packing_items' => $trip->packingItems->map(fn ($item) => [
    ...$item->toArray(),
    'last_edited_by' => $this->editorName($item),
    'added_by' => $this->participantSummary($item->createdBy),
    'assigned_to' => $this->participantSummary($item->assignedTo),
]),
```

Add a small private helper to `TripController` (used by this serializer and re-used by Phase 04 for progress segments):

```php
/**
 * @return array{id: int, name: string, first_name: string, initials: string}|null
 */
private function participantSummary(?User $user): ?array
{
    if ($user === null) {
        return null;
    }

    $first = $user->first_name ?: explode(' ', (string) $user->name)[0] ?? '';
    $last = $user->last_name ?? null;
    $initials = strtoupper(mb_substr($first, 0, 1).mb_substr((string) $last, 0, 1));

    return [
        'id' => $user->id,
        'name' => $user->name,
        'first_name' => $first ?: $user->name,
        'initials' => $initials !== '' ? $initials : strtoupper(mb_substr($user->name, 0, 1)),
    ];
}
```

The frontend `PackingItem` type gains:

```ts
added_by: { id: number; name: string; first_name: string; initials: string } | null;
assigned_to: { id: number; name: string; first_name: string; initials: string } | null;
assigned_to_user_id: number | null;
```

## Trip Participants Payload (frozen)

The frontend assignee dropdown needs to know who can be assigned. Add a single new field to the trip serializer (next to `collaborators`):

```php
'participants' => $this->participantsList($trip),
```

```php
/**
 * @return array<int, array{id:int,name:string,first_name:string,initials:string,role:'owner'|'editor'|'viewer'}>
 */
private function participantsList(Trip $trip): array
{
    $owner = $trip->user;
    $owner_summary = array_merge($this->participantSummary($owner) ?? [], ['role' => 'owner']);

    $collaborators = $trip->collaborators
        ->whereNotNull('user_id')
        ->map(function ($collab) {
            $summary = $this->participantSummary($collab->user);
            return $summary ? array_merge($summary, ['role' => $collab->role]) : null;
        })
        ->filter()
        ->values()
        ->all();

    return collect([$owner_summary])->merge($collaborators)->unique('id')->values()->all();
}
```

The frontend uses `props.trip.participants` to populate the assignee picker.

## Endpoint Contract (frozen)

No new routes. The existing `POST /trips/{trip}/packing-items` and `PATCH /trips/{trip}/packing-items/{packingItem}` accept the new field through `validatedPacking()`. The existing `PATCH .../packed` toggle endpoint (`packing-checkbox` module) is unchanged — assignment is independent of packed state.

The activity event produced by the create / update flows is unchanged in shape (`packing.created`, `packing.updated`). When `assigned_to_user_id` changes through `updatePacking`, no separate `packing.reassigned` event fires for now — it's part of the umbrella `packing.updated`. (Re-evaluate in a follow-up if the activity feed reads ambiguously.)

## Open Questions To Resolve Before Phase 02

- Confirm "no separate `packing.reassigned` event" — keep reassignment under `packing.updated`, or split it for clarity.
- Confirm migration name and timestamp slot (next available after `2026_05_06_015922_*`).
- Confirm `participantSummary()` lives on `TripController` for now — promote to a dedicated resource class if a second controller needs it.

Record answers in `ai/state/packing-attribution.json` `decisions[]` before Phase 02.

## State Management

- Server is the source of truth for `assigned_to_user_id` (DB column).
- Frontend never caches the participants list independently — it re-reads `props.trip.participants` on every Inertia visit.
- No new optimistic state in this phase — assignment changes go through the existing edit form, which already does a full visit.

## Acceptance Criteria

- Migration is written and runs cleanly forward and backward against a fresh DB.
- `PackingItem` model exposes the new relation and fillable.
- `validatedPacking()` accepts and validates `assigned_to_user_id` against trip participants.
- Trip detail serializer attaches `added_by`, `assigned_to`, and a `participants` array on the trip payload.
- Frontend `PackingItem` and `Trip` TypeScript shapes are extended in `resources/js/pages/Trips/Show.vue`.
- Open questions are answered in the state JSON.

## Out Of Scope

- Sending the targeted notification (Phase 02).
- Rendering the assignee picker or row attribution (Phase 03).
- Per-assignee progress segments or "Mine to pack" filter (Phase 04).
- Re-assignment from a row dropdown (deferred entirely).
