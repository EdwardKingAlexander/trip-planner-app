# Phase 03 - Trip Workspace And Full-Screen Forms

## Goal

Solve the single biggest mobile UX complaint: edit forms on the trip workspace open *inside the row* and bury the page in a wall of inputs. Replace that pattern with a full-screen `<Sheet>` on `<sm` that fills the viewport, sticks Save and Cancel at the bottom, and leaves the underlying list visually intact. Also address the cumulative density of `Trips/Show.vue` so the user reaches active content faster on a phone.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 02 (safe-area + sticky-header height stabilized)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue` — every edit-form branch on every panel:
  - Itinerary edit (~lines 905-925).
  - Reservation edit (~lines 832-925), now also containing the new flight-details `<details>` block.
  - Cost edit, packing edit, task edit, document edit, reminder edit (each per-panel).
- `resources/js/pages/Trips/Show.vue` panel header area — sticky tab bar + hero + stats grid + action cluster (the "above the fold" stack on mobile).
- All audit findings tagged `category: forms | density | gesture (swipe-on-form)`.
- Existing shadcn-vue `Sheet` component (used by the offcanvas nav) — confirm it can render side="bottom" and full-height variants.

## Strategy

Three threads:

1. **EditSheet pattern.** A single new `<EditSheet>` wrapper component that renders as:
   - Inline branch (today's behavior) when `viewport >= sm`.
   - Full-screen `<Sheet side="bottom">` filling the viewport when `viewport < sm`.
   The component is a slot host — every existing edit form is wrapped without rewriting its fields. Save and Cancel are sticky at the bottom inside the sheet, above the safe-area inset.
2. **Workspace density reset.** The "above the fold" stack at the top of `Trips/Show.vue` (hero, stats grid, three export buttons, sticky panel tabs) compresses on mobile so the user lands on actual content within one screen height. Hero condenses; stats grid hides on `<sm` (data still accessible via the trip details edit); export buttons move into a "More" dropdown on `<sm`.
3. **Section anchor mini-nav inside long forms.** The reservation edit form is the longest in the app — top fields, optional flight-details `<details>` with five subsections, attachments. Inside the EditSheet, a sticky horizontal pill scroller at the top jumps to each subsection so the user doesn't have to thumb-scroll past 15 fields to reach `flight_details.notes`.

## Planned Changes

### 1. `<EditSheet>` component (new)

`resources/js/components/EditSheet.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';

const props = defineProps<{
    open: boolean;
    title: string;
    submitLabel?: string;
    submitting?: boolean;
}>();

defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit'): void;
    (e: 'cancel'): void;
}>();

const isMobile = useMediaQuery('(max-width: 639px)');
</script>

<template>
    <!-- Mobile: full-screen bottom sheet -->
    <Sheet v-if="isMobile" :open="open" @update:open="$emit('update:open', $event)">
        <SheetContent side="bottom" class="flex h-[100dvh] flex-col gap-0 p-0">
            <SheetHeader class="flex-row items-center justify-between border-b border-border px-4 pt-safe-or-3 pb-3">
                <button
                    type="button"
                    class="travel-touch text-sm text-muted-foreground"
                    @click="$emit('cancel'); $emit('update:open', false)"
                >
                    Cancel
                </button>
                <SheetTitle class="text-base font-semibold">{{ title }}</SheetTitle>
                <button
                    type="button"
                    class="travel-button-primary text-sm"
                    :disabled="submitting"
                    @click="$emit('submit')"
                >
                    {{ submitLabel ?? 'Save' }}
                </button>
            </SheetHeader>
            <div class="flex-1 overflow-y-auto px-4 py-4 pb-safe-or-4">
                <slot />
            </div>
        </SheetContent>
    </Sheet>

    <!-- Desktop: inline (consumer renders the form themselves; this slot is a no-op wrapper) -->
    <div v-else-if="open" class="rounded-md border border-border p-3">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold">{{ title }}</h3>
            <div class="flex gap-2">
                <button type="button" class="travel-touch text-sm text-muted-foreground" @click="$emit('cancel'); $emit('update:open', false)">
                    Cancel
                </button>
                <button type="button" class="travel-button-primary text-sm" :disabled="submitting" @click="$emit('submit')">
                    {{ submitLabel ?? 'Save' }}
                </button>
            </div>
        </div>
        <slot />
    </div>
</template>
```

### 2. Wrapping the existing edit forms

Each per-panel edit form in `Trips/Show.vue` (itinerary, reservation, cost, packing, task, document, reminder) wraps its existing fields in `<EditSheet>`. The form's own `@submit.prevent="patchEdit(...)"` becomes an `@submit` event the sheet emits.

Example for reservation edit (today the form lives inside the card via `v-if="isEditing('reservation', reservation.id)"`):

```vue
<!-- Card stays in read-only branch always; the sheet replaces the inline branch -->
<template v-else>
    <!-- existing read-only markup unchanged -->
</template>

<!-- Single EditSheet at the page root, driven by editing.value -->
<EditSheet
    :open="editing?.type === 'reservation'"
    title="Edit reservation"
    :submitting="editFormProcessing"
    @update:open="(value) => { if (!value) cancelEdit() }"
    @cancel="cancelEdit"
    @submit="patchEdit(updateReservation.url({ trip: trip.id, reservation: editing!.id }))"
>
    <!-- all existing reservation edit form fields, copied from the inline branch -->
    <FormSectionPills v-if="hasFlightDetails" :sections="reservationSections" />
    <!-- form fields -->
</EditSheet>
```

The `editing` ref already exists and tracks `{type, id}`. The sheet binds to it; cancel resets it to `null`. No new state owners.

The inline desktop branch can either continue to render inside the card (current behavior) or also use the sheet (which falls back to a less obtrusive inline panel via the `v-else` branch). Pick one and stay consistent — recommended: use the sheet wrapper everywhere and let it pick the branch via `useMediaQuery`.

### 3. `<FormSectionPills>` component (new, for long forms only)

`resources/js/components/FormSectionPills.vue`:

```vue
<script setup lang="ts">
defineProps<{
    sections: Array<{ id: string; label: string }>;
}>();

const scrollToSection = (id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <div class="sticky top-0 z-10 -mx-4 mb-3 flex gap-2 overflow-x-auto bg-background px-4 py-2 ring-1 ring-border">
        <button
            v-for="section in sections"
            :key="section.id"
            type="button"
            class="travel-touch shrink-0 rounded-full border border-border px-3 text-xs"
            @click="scrollToSection(section.id)"
        >
            {{ section.label }}
        </button>
    </div>
</template>
```

Each major section in the reservation edit form gets an `id` attribute (`id="section-basic"`, `id="section-flight-details"`, `id="section-attachments"`, etc.). The pills jump to them. The pill bar is sticky at the top of the sheet's scroll container, just below the sheet header.

Render `<FormSectionPills>` only when the form has 3+ logical sections. Short forms (cost, task, reminder) don't get it.

### 4. Above-the-fold density reset

The current top stack on `Trips/Show.vue`:

1. Sticky header (~64 px after Phase 02).
2. Hero panel: back link + 3xl/5xl title + dates + summary (~180 px on `<sm`).
3. Stats grid: 3 stat cards (~80 px).
4. Action cluster: Print / ICS / JSON buttons (~50 px).
5. Sticky panel tabs grid (~96 px on `<sm` for 6 tabs in a 2-col grid).

Total: ~470 px before content. On a 667 px iPhone SE viewport that leaves ~200 px for actual data — typically just one packing item.

Mobile-only adjustments:

- Hero title `text-3xl` (already), drop the `sm:text-5xl` step (no need for tablet sizing on mobile).
- Hero summary truncates to 2 lines on `<sm` with a "more" expand.
- Stats grid hides on `<sm` (`sm:grid hidden`). The data (bookings/days/shared count) is also visible inside each tab; the duplicate at the top is desktop polish.
- Three export buttons collapse into a single "Export" dropdown on `<sm` (Print, ICS, JSON as menu items). On `≥sm` they stay as the three-button row.
- The 6-tab segmented grid stays — `navigation-uplift` polished it, and tabs in a 2-col grid are recognized. But it shouldn't be sticky on `<sm` (frees ~96 px once the user starts scrolling). The header is enough sticky chrome.

Total reclaimed on `<sm`: ~250 px. The user lands on data within the first screen.

### 5. Inline notes vs sheet conflict

A few panels use inline expand patterns that are *not* edit forms — e.g., the reservation card's collapsible Attachments section and Flight details `<details>` block. These stay as inline expands. They're not forms; they're details disclosure.

The split rule:

- **Forms (input + submit):** EditSheet on mobile.
- **Disclosures (read-only or single-toggle expand):** stay inline.

Apply this consistently when a future component is introduced.

## Planned Changes Summary

- `resources/js/components/EditSheet.vue` (new).
- `resources/js/components/FormSectionPills.vue` (new).
- `resources/js/pages/Trips/Show.vue` — wrap every edit branch in `<EditSheet>`; add `id` attributes to long-form sections; conditionally render `<FormSectionPills>` for the reservation edit form; collapse the export-button cluster into a dropdown on `<sm`; hide the stats grid on `<sm`; remove sticky from the panel tab grid on `<sm`.
- `resources/js/components/ui/dropdown-menu/*` (likely already present from shadcn-vue) — used for the Export dropdown.
- `resources/css/app.css` — add `travel-input` mobile-friendly styles if not already present (font-size ≥ 16 px to suppress iOS zoom-on-focus).

## State Management

- `editing` ref already exists in `Trips/Show.vue`. The EditSheet binds to it via a computed `open` flag. No new state owners.
- `editFormProcessing` derives from the existing `useForm`'s `processing` flag.
- `useMediaQuery('(max-width: 639px)')` — page-local; one shared instance per page is fine.
- Section pills use `document.getElementById` for jump-to behavior — no Vue ref needed.

## Tests

This phase is structural UI. Rely on:

- Phase 06 real-device matrix.
- `npm run types:check`, `npm run lint:check`, and `npm run build` as the required gates.
- A Pest browser smoke (Pest 4 / Playwright) `tests/Browser/MobileEditSheetTest.php`:
  - Visit `/trips/{id}` at viewport 390×844.
  - Tap Edit on a packing item. Assert a full-screen sheet opens with Cancel and Save in the header.
  - Tap Cancel. Assert the sheet closes and the page is unchanged.
  - Tap Edit again, change the label, tap Save. Assert the sheet closes and the row text updates.
  - Visit at 1280×800. Tap Edit. Assert no full-screen sheet — the inline edit form (or the desktop fallback) appears.

## Acceptance Criteria

- Every edit form on `Trips/Show.vue` renders as a full-screen sheet on `<sm` with Cancel/Save sticky at the bottom (above the safe area).
- Same forms render inline on `≥sm` exactly as today.
- The reservation edit form (the longest) shows `<FormSectionPills>` at the top with jump-to anchors for at least: Basics, Flight details, Attachments.
- The trip workspace top-of-page stack on iPhone SE shows the hero title + dates + the active panel's first item without scrolling.
- The export-button cluster collapses to a dropdown on `<sm`.
- The stats grid hides on `<sm`.
- The panel tab grid is not sticky on `<sm`.
- Form inputs use `font-size: 16px` minimum so iOS does not zoom on focus.
- All audit findings tagged `forms | density | gesture (form-related)` are addressed.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Replacing form validation libraries (the existing Inertia `useForm` stays).
- Adding autosave-on-blur (deferred — explicit Save is the right pattern for shared trips where collaborators see updates).
- Per-panel deep redesign of read-only rows (Phase 05 covers row touch affordances).
- Input-type / OS picker fixes (Phase 04).
- Replacing the existing tab-grid component itself.
- Migrating to a different layout for the trip workspace on desktop.
- Server-side changes — this phase is pure frontend.
