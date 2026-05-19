# Phase 02 - Field-Level Errors And Submit Feedback

## Goal

Make every server validation failure on the Add Reservation form visible: an `InputError` under each input, a summary block at the top of the form listing the first three issues, a toast on every 422, and a `scrollToFirstError` that focuses the offending input. The single biggest cause of "I can't make another reservation" is silence — this phase ends the silence.

Apply the same treatment to the inline edit reservation form (lines 1276-1411) so the DNA bug doesn't continue living on the edit path.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01 (`audit_findings` populated so we know which fields actually need wiring)
- Blocker: none

## Inputs To Surface (from `validatedReservation`)

Every input that exists on the add form gets a matching `InputError`. From the Phase 01 audit:

- `type` — `reservationForm.errors.type`
- `title` — already wired
- `provider_name` — `reservationForm.errors.provider_name`
- `booking_reference` — `reservationForm.errors.booking_reference`
- `starts_at` — `reservationForm.errors.starts_at`
- `starts_timezone` — `reservationForm.errors.starts_timezone`
- `ends_at` — `reservationForm.errors.ends_at`
- `ends_timezone` — `reservationForm.errors.ends_timezone`
- `location_name` — `reservationForm.errors.location_name`
- `address` — `reservationForm.errors.address`
- `notes` — `reservationForm.errors.notes`
- `airline`, `flight_number`, `departure_airport`, `arrival_airport` — for `type === 'flight'`
- `property_name`, `room_type` — for `type === 'lodging'`

`contact_phone` and `contact_email` are not on the add form. Leave them out; they'll come in on the edit form below.

The edit form (lines 1276-1411) has all of the above **plus** `contact_phone`, `contact_email`, and the `flight_details` nested object. Wire `InputError` for every one. For nested errors (`flight_details.cabin_class`, etc.), Inertia exposes them under `editForm.errors['flight_details.cabin_class']`. A small helper makes that ergonomic.

## New Component: `FormErrorSummary`

`resources/js/components/FormErrorSummary.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    errors: Record<string, string>;
    max?: number;
}>();

const entries = computed(() => {
    const max = props.max ?? 3;
    return Object.entries(props.errors).slice(0, max);
});

const hidden = computed(() => Math.max(0, Object.keys(props.errors).length - (props.max ?? 3)));
</script>

<template>
    <div
        v-if="entries.length"
        role="alert"
        class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
    >
        <p class="font-medium">Couldn't save — please fix:</p>
        <ul class="mt-1 list-disc pl-5">
            <li v-for="[field, message] in entries" :key="field">{{ message }}</li>
        </ul>
        <p v-if="hidden > 0" class="mt-1 text-xs text-destructive/80">…and {{ hidden }} more.</p>
    </div>
</template>
```

Used at the top of both the add form and the edit form:

```vue
<form class="grid gap-3" @submit.prevent="onSubmitReservation">
    <FormErrorSummary :errors="reservationForm.errors" />
    <!-- existing fields, each followed by their InputError -->
</form>
```

## New Helper: `scrollToFirstError`

`resources/js/lib/scrollToFirstError.ts`:

```ts
/**
 * After an Inertia form post fails, focus the first invalid input.
 *
 * Looks for an element with `name="<errorKey>"` first, then
 * `[data-error-target="<errorKey>"]` as an escape hatch for inputs that
 * can't carry a `name` attribute (custom selects, comboboxes).
 */
export function scrollToFirstError(errors: Record<string, string>, container?: HTMLElement | null) {
    const firstKey = Object.keys(errors)[0];
    if (!firstKey) return;

    const root = container ?? document;
    const selector = `[name="${firstKey}"], [data-error-target="${firstKey}"]`;
    const target = root.querySelector<HTMLElement>(selector);
    if (!target) return;

    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if ('focus' in target && typeof target.focus === 'function') {
        target.focus({ preventScroll: true });
    }
}
```

Pure function, no state, no side effects beyond DOM. Easy to unit test.

## Updated `post` Helper

`Trips/Show.vue` currently has (line 551-556):

```ts
const post = (form: ReturnType<typeof useForm>, url: string, resetFields?: string[]) => {
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => resetFields ? form.reset(...resetFields) : form.reset(),
    });
};
```

Replace with:

```ts
import { scrollToFirstError } from '@/lib/scrollToFirstError';
import { toast } from '@/lib/toast'; // existing flash renderer; verify path during Phase 02

const post = (form: ReturnType<typeof useForm>, url: string, resetFields?: string[]) => {
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => (resetFields ? form.reset(...resetFields) : form.reset()),
        onError: (errors) => {
            scrollToFirstError(errors);
            toast.error("Couldn't save — check the highlighted fields.");
        },
    });
};
```

The toast path is a safety net for any field whose `InputError` we miss. Same wrapper is already used by the packing / cost / task / document / reminder forms on this page — they all benefit from the upgrade with no other changes.

If a `toast` module doesn't exist in the project, Phase 02 ships the smallest possible one (`resources/js/lib/toast.ts` with `error()` and `success()` that push to a `usePage().props.flash` mirror), or — more likely — wires through the existing flash flow that the page already renders. Confirm during implementation.

## Wiring Each Input

Mechanical pass. Example for the timezone fields (lines 1606-1607):

Before:

```vue
<div class="grid gap-3 sm:grid-cols-2">
    <Input class="travel-touch" v-model="reservationForm.starts_timezone" placeholder="Start timezone" />
    <Input class="travel-touch" v-model="reservationForm.ends_timezone" placeholder="End timezone" />
</div>
```

After (Phase 02 only — picker comes in Phase 03):

```vue
<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <Input
            class="travel-touch"
            v-model="reservationForm.starts_timezone"
            name="starts_timezone"
            placeholder="Start timezone (e.g. America/Los_Angeles)"
        />
        <InputError :message="reservationForm.errors.starts_timezone" />
    </div>
    <div>
        <Input
            class="travel-touch"
            v-model="reservationForm.ends_timezone"
            name="ends_timezone"
            placeholder="End timezone (e.g. Asia/Manila)"
        />
        <InputError :message="reservationForm.errors.ends_timezone" />
    </div>
</div>
```

The `name` attribute is added on every input that maps to a server field. `scrollToFirstError` uses it. The wrapping `<div>` lets the `InputError` sit visually under the input without breaking the grid.

Repeat for every audit row from Phase 01.

## Same Treatment On The Edit Form

Lines 1276-1411 (the inline edit form when `isEditing('reservation', reservation.id)`) shares the bug. Same edits:

- Add `name="<field>"` to every input.
- Add `<InputError :message="editErrors[<field>]" />` under every input (the edit flow uses `editData` + `patchEdit`; confirm where its errors land — usually `usePage().props.errors` since the controller `back()`s with errors).
- Place `<FormErrorSummary :errors="editErrors" />` at the top.

The edit flow's `patchEdit` function (need to grep for it) also needs the `onError` path that the `post` helper now has. If `patchEdit` is a single shared function, the change is one line. If it's bespoke per form, refactor it to share.

## State Management

- `FormErrorSummary` is stateless (props only).
- `scrollToFirstError` is a pure function.
- `post` keeps its existing closure over the form; the new `onError` does not introduce state.
- The toast / flash render is a thin wrapper over existing `usePage().props.flash` infrastructure — no new global store.

## Tests

`tests/Feature/Trips/ReservationCreateErrorsTest.php`:

- `it returns validation errors for missing title` — POST without `title`, assert 422 + `errors.title`.
- `it returns validation errors for an invalid timezone` — POST with `starts_timezone = 'PST'`, assert 422 + `errors.starts_timezone`.
- `it returns validation errors for ends_at before starts_at` — POST reverse-order datetimes, assert 422 + `errors.ends_at`.
- `it returns validation errors for an oversized booking_reference` — 200-char string, assert 422 + `errors.booking_reference`.

Vitest (or Pest 4 browser, if Vitest isn't wired):

- `FormErrorSummary` renders the first three entries and an "and N more" line for the rest.
- `scrollToFirstError` calls `scrollIntoView` and `focus` on the matching `name` element. Stubbed DOM.

Pest 4 browser: `tests/Browser/ReservationCreateFeedbackTest.php`:

- Visit the trip page as the owner.
- Click "Add reservation".
- Type "PST" in the start timezone input.
- Submit.
- Assert the inline error "The starts timezone field must be a valid timezone." renders under the input.
- Assert the toast renders.
- Assert the page is still at the same scroll position.

## Acceptance Criteria

- Every input on the Add Reservation form has a matching `InputError`.
- `FormErrorSummary` is mounted at the top of both the add form and the edit form.
- Submitting the add form with `starts_timezone = 'PST'` produces a visible inline error AND a toast — no more silence.
- The form scrolls to and focuses the first invalid input on failed submit.
- The shared `post` helper's new `onError` path also benefits the packing / cost / task / document / reminder forms with no further changes (regression check that they all still post successfully on the happy path).
- The inline edit reservation form (lines 1276-1411) shows field-level errors with the same treatment.
- `npm run lint:check`, `npm run types:check`, `npm run build` all pass.
- `php artisan test --compact --filter='ReservationCreateErrors'` is green.
- The Pest 4 browser feedback test is green.
- `vendor/bin/pint --dirty --format agent` clean (likely a no-op; this phase is mostly Vue / TS).

## Out Of Scope

- Replacing the freeform timezone inputs with a picker (Phase 03).
- Changing the validator (Phase 04).
- Removing the `after_or_equal:starts_at` mismatch with cross-tz semantics (Phase 04).
- A toast / flash component swap.
- Refactoring the giant `Trips/Show.vue` file into smaller modules.
- Adding flight_details to the add form.
