# Phase 03 - Frontend Flight Details Form

## Goal

Wire a collapsible "Flight details" section into the existing reservation edit form on `Trips/Show.vue`. Sectioned for scan-ability (Cabin & pricing → Baggage allowance → Travel docs → Notes), every field nullable, currency dropdown defaults to `trip.suggested_currency` on first edit. Visible only when the reservation is type `flight`.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phase 02 (validation + sync logic shipped, serializer carries `flight_details` and `trip.suggested_currency`)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue:171-188` — current `reservationForm` shape. Needs nested `flight_details` keys.
- `resources/js/pages/Trips/Show.vue:171,907-917` — `startEdit('reservation', {...})` populates `editData` for the per-row edit form. The flight details preload happens here.
- `resources/js/pages/Trips/Show.vue:832-925` — current reservation card markup (read-only and edit branches).
- `resources/js/pages/Trips/Show.vue` reservation type definition — needs `flight_details` and `document_count` (the latter already added by `document-uploads` Phase 01).
- `resources/js/components/ui/select` — confirm a Select component exists for the cabin / currency dropdowns; otherwise fall back to native `<select>` styled with `travel-input`.

## Strategy

The reservation edit form already lives inside an `isEditing('reservation', reservation.id)` branch with its own form helpers. The simplest path is to extend that branch with a new `<details>` block titled "Flight details" that:

1. Only renders when `editData.type === 'flight'`.
2. Auto-expands when any flight-details field on the reservation already has a value.
3. Stays collapsed when every field is null (so the form doesn't grow unnecessarily for users who never use it).
4. Submits as a nested `flight_details` object on the existing `updateReservation` POST — Inertia handles nested form state out of the box.

A small composable `useFlightDetailsForm.ts` is **not** required — the form is page-local and uses the existing `editData` ref. Keep the logic inline.

## Planned Changes

### 1. Type updates

`resources/js/pages/Trips/Show.vue`:

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

// Reservation:
flight_details: FlightDetails | null;

// Trip:
suggested_currency: string;
```

### 2. Form preload

The existing `startEdit('reservation', {...})` call already spreads the reservation. Extend it so the `flight_details` shape is preloaded into `editData` with all keys present (even when null), so two-way binding works on every input from frame zero:

```ts
const blankFlightDetails = (suggested: string): FlightDetails => ({
    id: 0,
    reservation_id: 0,
    cabin_class: null,
    currency: suggested,
    carry_on_size: null,
    carry_on_weight: null,
    carry_on_fee: null,
    personal_item_size: null,
    personal_item_weight: null,
    personal_item_fee: null,
    checked_bag_size: null,
    checked_bag_weight: null,
    checked_bag_fee: null,
    additional_checked_bag_fee: null,
    additional_checked_bag_allowance: null,
    visa_requirement: null,
    passport_validity_rule: null,
    layover_notes: null,
    online_check_in_opens: null,
    boarding_closes: null,
    notes: null,
});

// In the existing startEdit call for reservations, replace the spread:
startEdit('reservation', {
    ...reservation,
    airline: reservation.flight_segments?.[0]?.airline ?? reservation.provider_name ?? '',
    flight_number: reservation.flight_segments?.[0]?.flight_number ?? '',
    departure_airport: reservation.flight_segments?.[0]?.departure_airport ?? '',
    arrival_airport: reservation.flight_segments?.[0]?.arrival_airport ?? '',
    property_name: reservation.lodging_stay?.property_name ?? reservation.title,
    room_type: reservation.lodging_stay?.room_type ?? '',
    flight_details: reservation.flight_details ?? blankFlightDetails(trip.value.suggested_currency),
});
```

When the user opens an existing flight that has no `flight_details` row yet, the form starts with the suggested currency pre-filled and every other field empty.

### 3. Section markup

Inside the existing `isEditing('reservation', reservation.id)` branch, after the basic reservation fields and before the submit row, add:

```vue
<details
    v-if="editData.type === 'flight'"
    class="mt-3 rounded-md border border-border p-3"
    :open="hasAnyFlightDetailsValue(editData.flight_details)"
>
    <summary class="cursor-pointer text-sm font-medium">
        <span class="inline-flex items-center gap-2">
            <Plane class="h-4 w-4" />
            Flight details
            <span class="text-xs text-muted-foreground">
                ({{ filledFlightDetailsCount(editData.flight_details) }} field{{ filledFlightDetailsCount(editData.flight_details) === 1 ? '' : 's' }} filled)
            </span>
        </span>
    </summary>

    <div class="mt-3 grid gap-4">
        <!-- Cabin & pricing -->
        <fieldset class="grid gap-2">
            <legend class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Cabin &amp; pricing</legend>
            <div class="grid gap-2 min-[460px]:grid-cols-2">
                <select v-model="editData.flight_details.cabin_class" class="travel-input">
                    <option :value="null">Cabin (optional)</option>
                    <option value="economy">Economy</option>
                    <option value="premium_economy">Premium Economy</option>
                    <option value="business">Business</option>
                    <option value="first">First</option>
                </select>
                <Input
                    class="travel-touch"
                    v-model="editData.flight_details.currency"
                    placeholder="Currency (e.g. USD)"
                    maxlength="3"
                    @blur="editData.flight_details.currency = (editData.flight_details.currency ?? '').toUpperCase() || null"
                />
            </div>
        </fieldset>

        <!-- Baggage allowance -->
        <fieldset class="grid gap-3">
            <legend class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Baggage allowance</legend>

            <BaggageRow
                label="Carry-on"
                :size="editData.flight_details.carry_on_size"
                :weight="editData.flight_details.carry_on_weight"
                :fee="editData.flight_details.carry_on_fee"
                :currency="editData.flight_details.currency"
                @update:size="editData.flight_details.carry_on_size = $event"
                @update:weight="editData.flight_details.carry_on_weight = $event"
                @update:fee="editData.flight_details.carry_on_fee = $event"
            />
            <BaggageRow
                label="Personal item / extra carry"
                :size="editData.flight_details.personal_item_size"
                :weight="editData.flight_details.personal_item_weight"
                :fee="editData.flight_details.personal_item_fee"
                :currency="editData.flight_details.currency"
                @update:size="editData.flight_details.personal_item_size = $event"
                @update:weight="editData.flight_details.personal_item_weight = $event"
                @update:fee="editData.flight_details.personal_item_fee = $event"
            />
            <BaggageRow
                label="Checked bag (first / included)"
                :size="editData.flight_details.checked_bag_size"
                :weight="editData.flight_details.checked_bag_weight"
                :fee="editData.flight_details.checked_bag_fee"
                :currency="editData.flight_details.currency"
                @update:size="editData.flight_details.checked_bag_size = $event"
                @update:weight="editData.flight_details.checked_bag_weight = $event"
                @update:fee="editData.flight_details.checked_bag_fee = $event"
            />

            <div class="grid gap-2 min-[460px]:grid-cols-2">
                <Input
                    class="travel-touch"
                    v-model="editData.flight_details.additional_checked_bag_fee"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Per extra checked bag fee"
                />
                <Input
                    class="travel-touch"
                    v-model="editData.flight_details.additional_checked_bag_allowance"
                    placeholder="Extra checked bag allowance (e.g. 'Up to 3')"
                />
            </div>
        </fieldset>

        <!-- Travel docs -->
        <fieldset class="grid gap-2">
            <legend class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Travel documents</legend>
            <textarea
                v-model="editData.flight_details.visa_requirement"
                class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                placeholder="Visa requirement (e.g. eTA Canada — apply 7 days ahead)"
            />
            <textarea
                v-model="editData.flight_details.passport_validity_rule"
                class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                placeholder="Passport validity rule (e.g. valid through 2027-01-15)"
            />
        </fieldset>

        <!-- Connection & check-in -->
        <fieldset class="grid gap-2">
            <legend class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Connection &amp; check-in</legend>
            <textarea
                v-model="editData.flight_details.layover_notes"
                class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                placeholder="Layover / connection notes (terminal change, minimum connection time, separate ticket Y/N, baggage re-check)"
            />
            <div class="grid gap-2 min-[460px]:grid-cols-2">
                <Input
                    class="travel-touch"
                    v-model="editData.flight_details.online_check_in_opens"
                    placeholder="Online check-in opens (e.g. 24h before)"
                    maxlength="80"
                />
                <Input
                    class="travel-touch"
                    v-model="editData.flight_details.boarding_closes"
                    placeholder="Boarding closes (e.g. 30 min before)"
                    maxlength="80"
                />
            </div>
        </fieldset>

        <!-- Notes -->
        <fieldset class="grid gap-2">
            <legend class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Notes</legend>
            <textarea
                v-model="editData.flight_details.notes"
                class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                placeholder="Anything else: meal preference, frequent flyer #, lounge access, etc."
            />
        </fieldset>
    </div>
</details>
```

### 4. BaggageRow component

A small inline component (kept in `Trips/Show.vue` or extracted to `resources/js/components/BaggageRow.vue`):

```vue
<script setup lang="ts">
defineProps<{
    label: string;
    size: string | null;
    weight: string | null;
    fee: string | null;
    currency: string | null;
}>();

defineEmits<{
    (e: 'update:size', value: string | null): void;
    (e: 'update:weight', value: string | null): void;
    (e: 'update:fee', value: string | null): void;
}>();
</script>

<template>
    <div class="grid gap-2 rounded-md bg-muted/30 p-2">
        <div class="text-xs font-medium">{{ label }}</div>
        <div class="grid gap-2 min-[460px]:grid-cols-3">
            <Input
                :model-value="size"
                @update:model-value="$emit('update:size', ($event as string) || null)"
                placeholder="Size (e.g. 55×40×20 cm)"
                class="travel-touch"
            />
            <Input
                :model-value="weight"
                @update:model-value="$emit('update:weight', ($event as string) || null)"
                placeholder="Weight (e.g. 7 kg)"
                class="travel-touch"
            />
            <div class="relative">
                <Input
                    :model-value="fee"
                    @update:model-value="$emit('update:fee', ($event as string) || null)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Fee"
                    class="travel-touch pr-10"
                />
                <span v-if="currency" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">
                    {{ currency }}
                </span>
            </div>
        </div>
    </div>
</template>
```

The currency code is shown as a passive suffix in the fee input — the user picks currency once at the top of the section and it labels every fee field.

### 5. Submission helpers

```ts
const hasAnyFlightDetailsValue = (fd: FlightDetails | null | undefined): boolean => {
    if (!fd) return false;
    const keys: (keyof FlightDetails)[] = [
        'cabin_class', 'currency', 'carry_on_size', 'carry_on_weight', 'carry_on_fee',
        'personal_item_size', 'personal_item_weight', 'personal_item_fee',
        'checked_bag_size', 'checked_bag_weight', 'checked_bag_fee',
        'additional_checked_bag_fee', 'additional_checked_bag_allowance',
        'visa_requirement', 'passport_validity_rule',
        'layover_notes', 'online_check_in_opens', 'boarding_closes',
        'notes',
    ];
    return keys.some((k) => fd[k] !== null && fd[k] !== '' && fd[k] !== undefined);
};

const filledFlightDetailsCount = (fd: FlightDetails | null | undefined): number => {
    if (!fd) return 0;
    return Object.values(fd).filter((v) => v !== null && v !== '' && v !== undefined && typeof v !== 'object').length;
};
```

### 6. Submit-time normalization

Empty strings on the form must travel as `null` to the server so the lazy-delete logic in Phase 02 fires correctly. Add a small normalization step inside the existing `patchEdit` helper (or just inside the form's `@submit.prevent` handler):

```ts
const normalizeFlightDetailsForSubmit = (fd: FlightDetails | null | undefined): Record<string, unknown> | null => {
    if (!fd) return null;
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(fd)) {
        if (k === 'id' || k === 'reservation_id') continue;
        out[k] = v === '' ? null : v;
    }
    return out;
};

// At submit time, before the patch:
const payload = {
    ...editData.value,
    flight_details: normalizeFlightDetailsForSubmit(editData.value.flight_details),
};
```

Currency uppercase coercion lives on the input's `@blur` (already shown above) so the user can type `usd` and see it become `USD` before submission.

### 7. Mobile considerations

- The `<details>` summary and section legends collapse cleanly on narrow viewports.
- The 3-column BaggageRow drops to a single column below `min-[460px]`.
- Currency suffix inside the fee input remains right-anchored; tap targets stay above the `min-h-11` floor used elsewhere.

## State Management

- `editData.flight_details` is part of the existing per-row edit form state — owned by the page, lives only while the form is open.
- `trip.suggested_currency` is read once when populating the form; never persisted client-side.
- No optimistic updates; submission is a full Inertia visit through the existing `updateReservation` route.
- Form errors come back through `editFormErrors` (or whatever the existing reservation edit helper exposes); render `<InputError :message="editFormErrors['flight_details.cabin_class']" />` next to each field.

## Tests

This phase is primarily UI; rely on:

- Phase 02 backend tests (proves payload roundtrips and validates correctly).
- A type check (`npm run types:check`) is the required gate.
- Optional Pest browser smoke (matching the verification approach used by neighbouring modules):
  - Open a flight reservation, click Edit, expand "Flight details".
  - Confirm the cabin select renders four options + the null option.
  - Confirm the currency input shows `USD` (or whatever the cascade returned) by default.
  - Type a carry-on size, save, reload — confirm the value persists and the section auto-expands on next edit.

## Acceptance Criteria

- The reservation edit form shows a collapsible "Flight details" `<details>` block only when `editData.type === 'flight'`.
- The block auto-expands when at least one field on the reservation already has a value; stays collapsed when none do.
- Cabin class, currency, three baggage rows (size/weight/fee), additional-checked fields, visa rule, passport rule, and notes are all editable.
- Currency input uppercases on blur and validates as 3 letters.
- The currency suffix labels every fee input.
- Submitting saves the nested payload through the existing `updateReservation` route; nullable behavior survives a round trip (clearing all fields deletes the row).
- Server validation errors surface on the right field.
- All existing reservation tests still pass.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Read-only summary / expanded display on the reservation card (Phase 04).
- Showing the section in the **create** form. This module's edit form covers create implicitly (the existing reservation create flow uses a separate form; users who want to add details on day one open the row's Edit immediately after creation).
- Per-segment overrides UI.
- Currency picker as a typeahead with full ISO 4217 suggestion list — a free-text 3-letter input is acceptable for v1.
- Auto-saving as the user types.
