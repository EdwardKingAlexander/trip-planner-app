# Phase 03 - Timezone Picker In The Form

## Goal

Replace the freeform `starts_timezone` / `ends_timezone` `<Input>`s on both the add and edit reservation forms with a constrained `TimezoneSelect` picker backed by `\DateTimeZone::listIdentifiers()`. After this phase ships, the timezone field cannot produce a 422 — it can only contain a valid IANA name. This removes the largest single source of "I can't make another reservation."

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 02 (field-level errors are visible; this phase makes them rare for timezone fields)
- Blocker: none

## Coordination With `trip-timezones`

This module and `ai/modules/trip-timezones/` both touch timezone UX. To avoid duplicating work:

- If `trip-timezones/02-schema-and-timezone-fields.md` has shipped, reuse its `TimezonePicker` component at `resources/js/components/TimezonePicker.vue`. The reservation form imports it as-is.
- If it has not shipped, build the picker here under a smaller name (`TimezoneSelect`) and lift it to the shared name when `trip-timezones` Phase 02 lands. The component contract is identical (props: `modelValue`, `defaultTimezone`, `errorTarget`; emits: `update:modelValue`).
- Either way, the **option list** is the same: `\DateTimeZone::listIdentifiers()` shipped once as a shared Inertia prop.

## Shared Prop: `timezones`

In `app/Http/Middleware/HandleInertiaRequests.php`, add to the shared props:

```php
'timezones' => Cache::rememberForever('inertia.timezones', fn () => \DateTimeZone::listIdentifiers()),
```

The list is ~400 entries. Cached forever; invalidates only on PHP upgrade.

Frontend reads via `usePage().props.timezones`.

## Component: `TimezoneSelect`

`resources/js/components/TimezoneSelect.vue` (or `TimezonePicker.vue` if `trip-timezones` has shipped):

- Combobox UI. Use the existing shadcn-vue `Combobox` if it's installed in the project; otherwise fall back to a `<select>` with a `<datalist>` for filtering. Confirm during implementation.
- Options: `usePage().props.timezones` grouped by the first path segment (`Africa`, `America`, `Asia`, `Atlantic`, `Australia`, `Europe`, `Indian`, `Pacific`, plus `UTC`).
- "Recent" section pinned at the top. Sources (in priority order):
  1. The current value.
  2. The trip's `destination_timezone` (when `trip-timezones` Phase 02 has shipped).
  3. The trip's `home_timezone`.
  4. The user's browser timezone (`Intl.DateTimeFormat().resolvedOptions().timeZone`).
  5. `UTC`.
- Search is case-insensitive and matches on the IANA name and a small synonyms map:

```ts
const synonyms: Record<string, string> = {
    pst: 'America/Los_Angeles',
    pdt: 'America/Los_Angeles',
    mst: 'America/Denver',
    mdt: 'America/Denver',
    cst: 'America/Chicago',
    cdt: 'America/Chicago',
    est: 'America/New_York',
    edt: 'America/New_York',
    gmt: 'UTC',
    utc: 'UTC',
    uk: 'Europe/London',
    british: 'Europe/London',
    manila: 'Asia/Manila',
    philippines: 'Asia/Manila',
    tokyo: 'Asia/Tokyo',
    japan: 'Asia/Tokyo',
    sydney: 'Australia/Sydney',
    hawaii: 'Pacific/Honolulu',
};
```

Typing `pst` shows `America/Los_Angeles` as the top result. Selecting it stores the IANA name, not the synonym.

- Label format on options: `Asia/Manila — PHT, UTC+8`. The short label is computed at render time via:

```ts
function shortOffset(tz: string): string {
    try {
        const parts = new Intl.DateTimeFormat(undefined, { timeZone: tz, timeZoneName: 'shortOffset' })
            .formatToParts(new Date());
        return parts.find((p) => p.type === 'timeZoneName')?.value ?? '';
    } catch {
        return '';
    }
}
```

Wrapped in a try/catch because some browsers/locales throw on unsupported tz names.

- `data-error-target` attribute on the trigger element matches the form field name so `scrollToFirstError` (Phase 02) finds it.

## Form Integration

Replace lines 1606-1607 (add form):

```vue
<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <TimezoneSelect
            v-model="reservationForm.starts_timezone"
            :default-timezone="defaultStartsTimezone"
            error-target="starts_timezone"
            label="Start timezone"
        />
        <InputError :message="reservationForm.errors.starts_timezone" />
    </div>
    <div>
        <TimezoneSelect
            v-model="reservationForm.ends_timezone"
            :default-timezone="defaultEndsTimezone"
            error-target="ends_timezone"
            label="End timezone"
        />
        <InputError :message="reservationForm.errors.ends_timezone" />
    </div>
</div>
```

`defaultStartsTimezone` / `defaultEndsTimezone` are computed once at form mount:

```ts
const defaultStartsTimezone = computed(() =>
    trip.value.destination_timezone
        ?? trip.value.home_timezone
        ?? browserTimezone
        ?? 'UTC',
);
```

Same wiring on the inline edit form (lines 1276-1411) for both `starts_timezone` and `ends_timezone`.

## "Pair Linking" Affordance

A small toggle next to the timezone pair: **🔗 Same as start**. When on, the end picker is hidden and `ends_timezone` mirrors `starts_timezone`. Default on (matches the common case: a hotel stay has check-in and check-out in the same tz). For flights, the user toggles it off and picks the arrival tz.

State: a local `ref<boolean>` per form (no Vue store).

## Server-Side Tightening

Replace `'timezone'` Laravel rule with `Rule::in(\DateTimeZone::listIdentifiers())`. Reason: ensures the picker's option list and the validator's accept list are the exact same set. The `timezone` rule uses a slightly different list on some PHP versions (deprecated aliases). This is a paranoia check; in practice the existing rule probably works fine.

In `TripReservationController::validatedReservation`:

```php
$timezones = \DateTimeZone::listIdentifiers();

return $request->validate([
    // ...
    'starts_timezone' => ['required', Rule::in($timezones)],
    'ends_timezone' => ['required', Rule::in($timezones)],
    // ...
]);
```

(Could also be a shared `TimezoneRule` if `trip-timezones` Phase 02 ships first; reuse it then.)

## State Management

- The timezone list is one shared Inertia prop. Frontend reads, never writes.
- `TimezoneSelect`'s open/close, search, and highlight are internal to the component.
- "🔗 Same as start" toggle is a local `ref` per form; no shared state.
- No new database columns.

## Tests

`tests/Feature/Trips/ReservationTimezonePickerTest.php`:

- `it accepts every identifier returned by listIdentifiers` — data-provider over the full list (or a random sample of 30), assert each posts cleanly.
- `it rejects shorthand timezone codes` — POST `starts_timezone = 'PST'`, assert 422.
- `it accepts UTC` — POST `starts_timezone = 'UTC'`, assert 201.

Vitest (if wired):

- `TimezoneSelect` shows `America/Los_Angeles` as the top result when typing `pst`.
- `TimezoneSelect` shows the "Recent" group when no input is typed.
- Selecting an option emits the IANA string, not the synonym.

## Acceptance Criteria

- Add form and edit form both use `TimezoneSelect` for both `starts_timezone` and `ends_timezone`.
- A user cannot submit a non-IANA value through the picker (no freeform input on the timezone field at all).
- Server-side rule swap is in place and tests pass.
- Pair-linking toggle works on both forms.
- `Rule::in(\DateTimeZone::listIdentifiers())` matches the option list shipped via shared prop.
- `npm run types:check`, `npm run build`, `php artisan test --compact --filter=ReservationTimezone` all green.
- `vendor/bin/pint --dirty --format agent` clean.

## Out Of Scope

- A geocode-based "auto-detect timezone from address" feature.
- Per-traveler default timezones (lives on `UserTravelPreference` already; not relevant here).
- Surfacing the picker on every other form on the page (`reminderForm`, `itineraryForm`) — those already have working timezone fields; touching them is a separate sweep.
- Animation when the picker opens.
- Localization of the timezone option labels beyond what `Intl` already provides.
