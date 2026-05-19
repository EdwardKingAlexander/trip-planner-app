# Phase 05 - Lists, Feedback, And Touch Affordances

## Goal

The polish layer. Make every row feel touchable (tap to edit, swipe to delete), every Inertia visit feel responsive (top progress bar already shipped in Phase 02; per-section skeletons live here), every form failure visible (scroll to first error), and every success acknowledged in a thumb-friendly toast position.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 02 (shell), 03 (sheet pattern), 04 (inputs)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue` — every list with row-level actions:
  - Itinerary items, reservations, costs, packing items, tasks, documents, reminders.
- `resources/js/pages/notifications/Index.vue` — notifications list.
- `resources/js/pages/Trips/Index.vue` — trip cards.
- The flash/toast renderer (Phase 02 set its position; this phase adds the dismiss UX).
- Inertia router events (`router.on('start' | 'finish' | 'error')`) — already wired for the top progress bar in Phase 02; reused here for skeleton timing.
- All audit findings tagged `category: lists | feedback | gesture | loading | toasts`.

## Strategy

Four small additions that compound:

1. **Swipe-to-reveal Delete on touch.** A tiny composable + a single `<SwipeRow>` wrapper. Swipe-left exposes a Delete button anchored to the trailing edge. Tap on the row body opens the edit sheet (or whatever the row's primary action is). Desktop renders inline Edit/Delete buttons exactly as today (no swipe).
2. **Per-section loading skeletons.** When a panel renders deferred props or a slow query, the existing pulsing skeleton pattern (already used by `inertia-laravel` deferred-props convention) stays. This phase adds skeletons where they're missing on the trip workspace's panels — specifically itinerary, reservations, and the documents tab.
3. **Scroll-to-first-error on form submit.** A small helper called from every Inertia `useForm.onError` callback. Walks the form's error keys, finds the first matching DOM element with `data-error-target="<key>"`, scrolls it into view with `scroll-margin-top` accounting for the sticky header, and focuses it.
4. **Toast dismiss + queue.** Phase 02 positioned the toast bottom-on-mobile. This phase adds a 44 × 44 close button, a 4-second auto-dismiss, and a small queue if multiple flash messages stack.

## Planned Changes

### 1. `<SwipeRow>` component (new)

`resources/js/components/SwipeRow.vue`:

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useMediaQuery, useSwipe } from '@vueuse/core';

const props = defineProps<{
    onPrimaryAction?: () => void;
    onDeleteAction?: () => void;
    deleteLabel?: string;
}>();

const rowEl = ref<HTMLElement | null>(null);
const isMobile = useMediaQuery('(max-width: 639px)');
const offset = ref(0);
const REVEAL_PX = 88;

const { lengthX } = useSwipe(rowEl, {
    threshold: 12,
    onSwipe: () => {
        if (!isMobile.value) return;
        offset.value = Math.max(-REVEAL_PX, Math.min(0, -lengthX.value));
    },
    onSwipeEnd: () => {
        if (!isMobile.value) return;
        offset.value = offset.value < -REVEAL_PX / 2 ? -REVEAL_PX : 0;
    },
});

const transform = computed(() => `translateX(${offset.value}px)`);

const handleRowTap = () => {
    if (offset.value < 0) {
        offset.value = 0;
        return;
    }
    props.onPrimaryAction?.();
};
</script>

<template>
    <div ref="rowEl" class="relative overflow-hidden">
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-[88px] items-center justify-center bg-destructive text-destructive-foreground"
            :aria-label="deleteLabel ?? 'Delete'"
            @click="onDeleteAction?.()"
        >
            {{ deleteLabel ?? 'Delete' }}
        </button>
        <div
            class="relative bg-card transition-transform"
            :style="{ transform }"
            @click="handleRowTap"
        >
            <slot />
        </div>
    </div>
</template>
```

Wrapping example (packing item):

```vue
<SwipeRow
    :on-primary-action="() => openEdit('packing', item)"
    :on-delete-action="() => destroyEntry(destroyPacking.url({ trip: trip.id, packingItem: item.id }), item.label)"
    delete-label="Delete"
>
    <!-- existing read-only row markup -->
</SwipeRow>
```

The SwipeRow renders inert on `≥sm` (no swipe; tap and the visible Edit/Delete buttons handle everything). The row content slot is identical to today; only the wrapper changes.

@vueuse/core may not be a dependency yet — if it isn't, add it (`useMediaQuery` and `useSwipe` are the only utilities needed; `useSwipe` could be hand-rolled with ~20 lines of Pointer Events to avoid the dependency, but `@vueuse/core` is small and broadly useful). Confirm via `package.json` before Phase 05 starts.

### 2. Per-section skeletons

Many tabs already render their data eagerly because the trip JSON is loaded in one request. Skeletons matter only where:

- The data is `Inertia::optional()` / `Inertia::defer()` — most likely the import suggestions and import batches.
- The first-paint of `Trips/Show.vue` is slow on a slow connection (>1 s to interactive).

Add a `<SkeletonRow>` component (shadcn-vue likely has one already; if not, add a minimal local one with `bg-muted animate-pulse` rounded blocks). Use it in:

- The notifications inbox while the JSON request is in flight.
- The import batches list (deferred prop pattern from `inertia-vue-development` skill).
- The trip detail page on initial load — but only if there's a measurable wait. Skip if the page is already snappy.

Don't add skeletons everywhere — they add visual noise. Audit findings tagged `loading` drive what gets one.

### 3. Scroll-to-first-error helper

`resources/js/lib/scrollToFirstError.ts` (new):

```ts
export function scrollToFirstError(errors: Record<string, string | string[]>) {
    const firstKey = Object.keys(errors)[0];
    if (!firstKey) return;

    const target =
        document.querySelector<HTMLElement>(`[data-error-target="${CSS.escape(firstKey)}"]`)
        ?? document.querySelector<HTMLElement>(`[name="${CSS.escape(firstKey)}"]`);

    if (!target) return;

    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    if (target.matches('input, textarea, select')) {
        (target as HTMLInputElement).focus({ preventScroll: true });
    }
}
```

Wire into every form submission:

```ts
form.post(url, {
    onError: (errors) => scrollToFirstError(errors),
});
```

For nested error keys (e.g. `flight_details.cabin_class`), make sure the form's input has either a matching `name` attribute or an explicit `data-error-target` attribute. The reservation edit form's flight-details fields should add `data-error-target="flight_details.cabin_class"` (etc.) since their `name` attributes may not include the dot path.

### 4. Toast dismiss + queue

Extend the flash-message renderer (positioned in Phase 02):

- Add a close button (X icon, 44 × 44 hit area).
- Auto-dismiss after 4 s for success, 6 s for error.
- If a new flash arrives while one is showing, queue it; show one at a time.
- Provide an `onClick` slot for action toasts (e.g., "Undo" — though no Undo flow exists today, the hook is cheap to add).

The simplest implementation: a single `<TransitionGroup>` rendering an array of toasts with a `setTimeout`-driven shift. State lives in the renderer component; no global store.

### 5. Tap states

Tailwind's `active:` variant is underused today. Add to every primary touch target:

- `active:scale-[0.98]` for tactile feedback on tap.
- `active:bg-primary/90` (or theme equivalent) for buttons.

Apply via the existing `travel-button-primary` and `travel-touch` classes in `resources/css/app.css` so it's a single edit point.

### 6. Long-press menu (optional, if audit calls for it)

If audit findings ask for a long-press menu (vs swipe-only), implement a `<LongPressMenu>` wrapper that:

- Listens for `pointerdown` + 600 ms hold.
- Opens a small contextual menu anchored to the touch point.
- Includes Edit and Delete (and any other actions per row type).

Don't ship this if no audit finding requires it — swipe + tap is enough for most users.

## Planned Changes Summary

- `resources/js/components/SwipeRow.vue` (new).
- `resources/js/components/SkeletonRow.vue` (new, if shadcn-vue's isn't already imported).
- `resources/js/lib/scrollToFirstError.ts` (new).
- `resources/js/pages/Trips/Show.vue` — wrap each row list with `<SwipeRow>`; add `data-error-target` attributes to inputs in long forms; pass `onError: scrollToFirstError` to every `useForm`.
- `resources/js/pages/notifications/Index.vue` — skeleton state; row-level swipe-to-mark-read.
- `resources/js/pages/Trips/Index.vue` — trip cards: tap target + active scale.
- Flash/toast renderer (located in Phase 02) — close button + auto-dismiss + queue.
- `resources/css/app.css` — add `active:` variants to `travel-button-primary` and `travel-touch`.
- `package.json` — add `@vueuse/core` if missing (already present in many shadcn-vue setups).

## State Management

- `<SwipeRow>` owns its own `offset` ref. Closing one row's swipe on opening another's is a feature deferred to Phase 06 polish if audit findings call for it.
- Toast queue lives in the toast renderer component — no global store.
- Skeletons are derived from existing `processing` / `loading` flags from Inertia — no new state.
- `scrollToFirstError` is a pure function — no state.

## Tests

This phase is UI polish. Rely on:

- Phase 06 real-device matrix for swipe gesture feel.
- `npm run types:check`, `npm run lint:check`, and `npm run build` as the required gates.
- Pest browser smokes:
  - `MobileSwipeRowTest`: at 390×844, swipe-left on a packing item, assert Delete reveals; tap Delete, assert the row is gone.
  - `ScrollToFirstErrorTest`: submit a reservation form with an invalid `flight_details.currency`. Assert the page scrolls to the currency input and the input is focused.
  - `ToastDismissTest`: trigger a success flash, assert it appears bottom-center on `<sm` with a close button, click close, assert it disappears immediately.

## Acceptance Criteria

- Every list row in `Trips/Show.vue` (packing, documents, reservations, costs, tasks, reminders, itinerary items) supports swipe-to-reveal-Delete on `<sm`. Tap on the row opens the edit sheet (Phase 03).
- Inline Edit/Delete buttons stay visible on `≥sm` exactly as today.
- Skeleton rows render for any panel that depends on a deferred prop or a measurably-slow first paint.
- Form submission scrolls the first invalid field into view and focuses it. The scroll respects the sticky-header height (via Phase 02's `scroll-margin-top` rules).
- Toast / flash messages auto-dismiss after 4 s (success) / 6 s (error), have a 44 × 44 close button, and queue if multiple arrive.
- Buttons and tappable rows have a visible `active:` state on tap.
- All audit findings tagged `lists | feedback | gesture | loading | toasts` are addressed.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Pull-to-refresh.
- Drag-to-reorder rows.
- Long-press context menu (unless an audit finding explicitly calls for it).
- Confetti / celebratory animations on success.
- Native haptic feedback (`navigator.vibrate` is too inconsistent across iOS Safari to ship).
- A global notification queue / `useToast()` API — keep the existing flash-message flow.
- Real-time row updates (already covered by `live-trip-collaboration`).
