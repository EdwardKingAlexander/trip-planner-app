# Phase 04 - Frontend Flight Details Read-Only Display

## Goal

When the reservation card is not in edit mode, surface flight details in two layers: a single-line summary that scans well at a glance ("Economy · Carry-on 55×40×20 cm 7 kg · Checked $30") and a "Show full details" toggle that reveals the rest in a structured panel. When no details are filled, render nothing — the card stays clean.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phase 03 (edit form shipped, payload populated)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue:889-925` — current read-only branch of the reservation card. The summary line slots above the `notes` paragraph; the expandable panel slots below it (above the Attachments section added by `document-uploads`).
- `resources/js/pages/Trips/Show.vue` reservation type — already has `flight_details: FlightDetails | null` after Phase 01.

## Strategy

Three small additions on the read-only branch:

1. **Inline summary line** — a single `<p>` rendered when `flight_details` exists and at least one summary-relevant field is filled. Always shown. Auto-truncates to one line on desktop (truncate utility), wraps to two lines on mobile.
2. **"Show full details" toggle button** — visible whenever `flight_details` has any non-null field that isn't already covered by the summary line (i.e. anything beyond cabin and the three primary bag specs). Toggles a `flightDetailsExpanded` Set keyed by reservation id.
3. **Expanded panel** — a structured grid with sections matching the form (Cabin & pricing, Baggage allowance, Travel docs, Notes). Empty fields are skipped — never render "—" or empty rows.

When `flight_details` is `null`, none of the three appears. The card looks identical to today.

## Planned Changes

### 1. Summary line composer (script block)

```ts
const flightDetailsSummary = (fd: FlightDetails | null | undefined): string | null => {
    if (!fd) return null;
    const parts: string[] = [];

    if (fd.cabin_class) {
        parts.push(cabinLabel(fd.cabin_class));
    }
    if (fd.carry_on_size || fd.carry_on_weight) {
        parts.push(formatBagSummary('Carry-on', fd.carry_on_size, fd.carry_on_weight));
    }
    if (fd.checked_bag_size || fd.checked_bag_weight || fd.checked_bag_fee) {
        const checked = formatBagSummary('Checked', fd.checked_bag_size, fd.checked_bag_weight);
        const fee = fd.checked_bag_fee ? formatPrice(fd.checked_bag_fee, fd.currency) : null;
        parts.push(fee ? `${checked} (${fee})` : checked);
    }
    return parts.length ? parts.join(' · ') : null;
};

const cabinLabel = (value: string): string => ({
    economy: 'Economy',
    premium_economy: 'Premium Economy',
    business: 'Business',
    first: 'First',
}[value] ?? value);

const formatBagSummary = (label: string, size: string | null, weight: string | null): string => {
    const dim = [size, weight].filter(Boolean).join(' ');
    return dim ? `${label} ${dim}` : label;
};

const formatPrice = (amount: string | null, currency: string | null): string => {
    if (!amount) return '';
    const cur = currency ?? '';
    return cur ? `${amount} ${cur}` : amount;
};
```

The summary intentionally only surfaces the most-asked-for facts (cabin + carry-on + checked). Personal item, additional checked, visa/passport/notes live in the expanded panel.

### 2. Expanded-state ref

```ts
const flightDetailsExpanded = ref<Set<number>>(new Set());

const isFlightDetailsExpanded = (reservationId: number) =>
    flightDetailsExpanded.value.has(reservationId);

const toggleFlightDetails = (reservationId: number) => {
    if (flightDetailsExpanded.value.has(reservationId)) {
        flightDetailsExpanded.value.delete(reservationId);
    } else {
        flightDetailsExpanded.value.add(reservationId);
    }
};
```

No `localStorage` persistence — the expanded state resets per page visit. This keeps `Trips/Show.vue` from accumulating per-trip expansion keys for what is fundamentally an info-on-demand affordance.

### 3. Predicate for the toggle

The toggle button shows when there is anything *beyond* the summary line worth revealing:

```ts
const hasExpandableFlightDetails = (fd: FlightDetails | null | undefined): boolean => {
    if (!fd) return false;
    return Boolean(
        fd.personal_item_size || fd.personal_item_weight || fd.personal_item_fee
        || fd.carry_on_fee
        || fd.additional_checked_bag_fee || fd.additional_checked_bag_allowance
        || fd.visa_requirement || fd.passport_validity_rule
        || fd.layover_notes || fd.online_check_in_opens || fd.boarding_closes
        || fd.notes
    );
};
```

Carry-on fee lives in the expandable panel because the summary line shows checked-bag fee only (most asked-after) and adding more parens to the summary degrades scannability.

### 4. Markup additions in the read-only branch

After the existing `<p v-if="reservation.notes">` paragraph (~line 896 today) and **before** the existing `last_edited_by` line (~line 897):

```vue
<template v-if="reservation.flight_details">
    <p
        v-if="flightDetailsSummary(reservation.flight_details)"
        class="mt-2 truncate text-sm text-muted-foreground"
        :title="flightDetailsSummary(reservation.flight_details)!"
    >
        <Plane class="inline h-3.5 w-3.5 mr-1 -mt-0.5" />
        {{ flightDetailsSummary(reservation.flight_details) }}
    </p>

    <button
        v-if="hasExpandableFlightDetails(reservation.flight_details)"
        type="button"
        class="mt-1 text-xs underline underline-offset-2 text-primary"
        @click="toggleFlightDetails(reservation.id)"
    >
        {{ isFlightDetailsExpanded(reservation.id) ? 'Hide full details' : 'Show full details' }}
    </button>

    <div
        v-if="isFlightDetailsExpanded(reservation.id)"
        class="mt-3 grid gap-3 rounded-md border border-border p-3 text-sm"
    >
        <!-- Cabin & pricing -->
        <div v-if="reservation.flight_details.cabin_class || reservation.flight_details.currency">
            <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Cabin &amp; pricing</div>
            <div class="mt-1">
                <span v-if="reservation.flight_details.cabin_class">
                    {{ cabinLabel(reservation.flight_details.cabin_class) }}
                </span>
                <span v-if="reservation.flight_details.currency" class="text-muted-foreground">
                    · prices in {{ reservation.flight_details.currency }}
                </span>
            </div>
        </div>

        <!-- Baggage allowance -->
        <div
            v-if="hasAnyBaggageInfo(reservation.flight_details)"
            class="space-y-2"
        >
            <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Baggage allowance</div>
            <BaggageSummaryRow
                label="Carry-on"
                :size="reservation.flight_details.carry_on_size"
                :weight="reservation.flight_details.carry_on_weight"
                :fee="reservation.flight_details.carry_on_fee"
                :currency="reservation.flight_details.currency"
            />
            <BaggageSummaryRow
                label="Personal item / extra carry"
                :size="reservation.flight_details.personal_item_size"
                :weight="reservation.flight_details.personal_item_weight"
                :fee="reservation.flight_details.personal_item_fee"
                :currency="reservation.flight_details.currency"
            />
            <BaggageSummaryRow
                label="Checked bag (first / included)"
                :size="reservation.flight_details.checked_bag_size"
                :weight="reservation.flight_details.checked_bag_weight"
                :fee="reservation.flight_details.checked_bag_fee"
                :currency="reservation.flight_details.currency"
            />
            <p
                v-if="reservation.flight_details.additional_checked_bag_fee || reservation.flight_details.additional_checked_bag_allowance"
                class="text-sm"
            >
                Additional checked bags:
                <template v-if="reservation.flight_details.additional_checked_bag_fee">
                    {{ formatPrice(reservation.flight_details.additional_checked_bag_fee, reservation.flight_details.currency) }} each
                </template>
                <template v-if="reservation.flight_details.additional_checked_bag_allowance">
                    · {{ reservation.flight_details.additional_checked_bag_allowance }}
                </template>
            </p>
        </div>

        <!-- Travel docs -->
        <div
            v-if="reservation.flight_details.visa_requirement || reservation.flight_details.passport_validity_rule"
            class="space-y-2"
        >
            <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Travel documents</div>
            <p v-if="reservation.flight_details.visa_requirement" class="rounded-md bg-amber-50 p-2 text-sm">
                <strong class="font-medium">Visa:</strong> {{ reservation.flight_details.visa_requirement }}
            </p>
            <p v-if="reservation.flight_details.passport_validity_rule" class="rounded-md bg-amber-50 p-2 text-sm">
                <strong class="font-medium">Passport:</strong> {{ reservation.flight_details.passport_validity_rule }}
            </p>
        </div>

        <!-- Connection & check-in -->
        <div
            v-if="reservation.flight_details.layover_notes || reservation.flight_details.online_check_in_opens || reservation.flight_details.boarding_closes"
            class="space-y-2"
        >
            <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Connection &amp; check-in</div>
            <p v-if="reservation.flight_details.layover_notes" class="rounded-md bg-muted p-2 text-sm whitespace-pre-line">
                {{ reservation.flight_details.layover_notes }}
            </p>
            <div
                v-if="reservation.flight_details.online_check_in_opens || reservation.flight_details.boarding_closes"
                class="grid gap-2 text-sm min-[460px]:grid-cols-2"
            >
                <div v-if="reservation.flight_details.online_check_in_opens" class="rounded-md border border-border p-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Check-in opens</div>
                    <div>{{ reservation.flight_details.online_check_in_opens }}</div>
                </div>
                <div v-if="reservation.flight_details.boarding_closes" class="rounded-md border border-border p-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Boarding closes</div>
                    <div>{{ reservation.flight_details.boarding_closes }}</div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div v-if="reservation.flight_details.notes" class="space-y-1">
            <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Notes</div>
            <p class="rounded-md bg-muted p-2 text-sm">{{ reservation.flight_details.notes }}</p>
        </div>
    </div>
</template>
```

### 5. BaggageSummaryRow component

A small read-only mirror of the form's `BaggageRow`:

```vue
<script setup lang="ts">
const props = defineProps<{
    label: string;
    size: string | null;
    weight: string | null;
    fee: string | null;
    currency: string | null;
}>();

const isEmpty = !props.size && !props.weight && !props.fee;
</script>

<template>
    <div v-if="!isEmpty" class="grid gap-1">
        <div class="text-xs font-medium">{{ label }}</div>
        <div class="text-sm text-muted-foreground">
            <template v-if="size">{{ size }}</template>
            <template v-if="size && weight"> · </template>
            <template v-if="weight">{{ weight }}</template>
            <template v-if="fee && (size || weight)"> · </template>
            <template v-if="fee">
                {{ fee }}<template v-if="currency"> {{ currency }}</template>
            </template>
        </div>
    </div>
</template>
```

`isEmpty` returns nothing for fully-blank bag rows so the panel doesn't accumulate empty section headers.

### 6. Helper for `hasAnyBaggageInfo`

```ts
const hasAnyBaggageInfo = (fd: FlightDetails): boolean =>
    Boolean(
        fd.carry_on_size || fd.carry_on_weight || fd.carry_on_fee
        || fd.personal_item_size || fd.personal_item_weight || fd.personal_item_fee
        || fd.checked_bag_size || fd.checked_bag_weight || fd.checked_bag_fee
        || fd.additional_checked_bag_fee || fd.additional_checked_bag_allowance
    );
```

### 7. Theme compatibility

- The amber background on visa / passport callouts uses Tailwind utility colors that read on every theme. If a theme overrides background tokens significantly, swap to `bg-primary/10 ring-1 ring-primary/30` so the color follows the active theme.
- Plane icon uses `lucide-vue-next` (already imported elsewhere in this file).
- The expand toggle uses `text-primary` so it tracks the active theme's accent.

### 8. Mobile considerations

- Summary line `truncate` keeps the card single-line on desktop; on mobile the line wraps but the inline icon stays anchored to the first word.
- The expanded panel uses `gap-3` between sections and stacks naturally — no special media query needed.

## State Management

- `flightDetailsExpanded: ref<Set<number>>` — page-local, resets per visit. No persistence.
- Summary text is computed inline by the helper functions — no caching.
- No backend changes in this phase.

## Tests

This phase is primarily UI; rely on:

- Phase 02 backend tests (data is correct).
- A type check (`npm run types:check`) is the required gate.
- Optional Pest browser smoke:
  - Render a trip with a flight that has cabin + carry-on + checked filled. Assert the summary line reads "Economy · Carry-on 55×40×20 cm 7 kg · Checked 30 USD".
  - Render a trip with a flight that has only the visa field filled. Assert the summary line is hidden but the "Show full details" toggle is visible.
  - Render a trip with a flight that has no `flight_details` row. Assert no extra UI on the card.

## Acceptance Criteria

- The reservation card shows a one-line summary when `flight_details` is non-null and any summary-eligible field is filled.
- The "Show full details" toggle appears only when at least one expandable field is filled.
- The expanded panel renders only the sections that have content; empty sections are omitted entirely.
- The card looks identical to today when `flight_details` is null.
- Visa and passport callouts are visually distinct from the regular notes block (amber or theme-accented background).
- All five themes render the new UI legibly.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Persisting the expanded state across visits.
- A "copy as text" or "share with collaborator" affordance.
- Currency conversion or auto-summing fees.
- Per-segment view (the summary is per-reservation only).
- Inline edit (toggling values without entering the full edit form).
- A printable version optimized for the trip print export — covered by `TripExportService` in a follow-up if needed.
