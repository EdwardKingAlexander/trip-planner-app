# Phase 04 - Progress Indicator And Polish

## Goal

Make the packing list feel like progress is being made: show how close to done the user is, let them hide what they've already packed, and make the unpacked items the visual focus.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 03 (checkbox functional)
- Blocker: none

## Planned Changes

### Progress header in `resources/js/pages/Trips/Show.vue` Packing panel

- Replace the current `<CardTitle class="text-base">Packing List</CardTitle>` with a progress header:
  ```
  Packing List · 12 of 30 packed
  [▰▰▰▰▰▰▰▰▱▱▱▱▱▱▱]
  ```
- Computed properties:
  ```ts
  const packedCount = computed(() =>
      trip.value.packing_items.filter((item) => item.is_packed).length,
  );
  const totalCount = computed(() => trip.value.packing_items.length);
  const packedFraction = computed(() =>
      totalCount.value === 0 ? 0 : packedCount.value / totalCount.value,
  );
  ```
- Progress bar uses a div with `bg-primary` width and `bg-muted` track, sized via `--primary` from the active theme so the bar adapts to whichever theme the user has from `themes-plan`.
- Empty list (zero items) shows the title only, no progress bar.

### "Hide packed" toggle

- New `Switch` (from `@/components/ui/switch` if it exists, else use a simple labeled checkbox) in the panel header, right side.
- Label: "Hide packed".
- State:
  ```ts
  const hidePacked = ref(false);
  const hidePackedKey = computed(() => `packing-hide-packed:${trip.value.id}`);
  onMounted(() => {
      hidePacked.value = localStorage.getItem(hidePackedKey.value) === '1';
  });
  watch(hidePacked, (value) => {
      localStorage.setItem(hidePackedKey.value, value ? '1' : '0');
  });
  ```
- Filtered list:
  ```ts
  const visiblePackingItems = computed(() =>
      hidePacked.value
          ? trip.value.packing_items.filter((item) => !item.is_packed)
          : trip.value.packing_items,
  );
  ```
- Replace the `v-for` source from `trip.packing_items` to `visiblePackingItems`.
- When the filter is on AND every item is packed, render a celebratory empty state: "Everything's packed. Bon voyage." with a small icon. (No emojis in code per project guidelines — use a `lucide-vue-next` icon like `CheckCircle2`.)

### Sort packed items to the bottom

- Independent of the hide-packed filter, packed items always sort below unpacked ones so the user's eye lands on what's left.
- Computed list:
  ```ts
  const orderedPackingItems = computed(() => {
      const list = visiblePackingItems.value;
      return [...list].sort((a, b) => {
          if (a.is_packed === b.is_packed) {
              return (a.sort_order ?? 0) - (b.sort_order ?? 0);
          }
          return a.is_packed ? 1 : -1;
      });
  });
  ```
- This is presentation-only — DB `sort_order` is untouched. If drag-and-drop reordering ships later, it can ignore packed items or include them; that's a future decision.

### Theme compatibility

- Progress bar uses `bg-primary` and `bg-muted` (theme tokens). All five themes from `themes-plan/03-five-premade-themes.md` already define these.
- "Hide packed" switch uses default `Switch` styling, which already consumes theme tokens.

### Mobile considerations

- Progress header stacks vertically below `sm:` breakpoint: title on row 1, progress bar on row 2, switch on row 3.
- Checkbox tap target keeps the existing `min-h-11` from the `travel-touch` class.

## State Management

- `hidePacked` ref + localStorage per-trip — small, self-contained.
- `packedCount`, `totalCount`, `packedFraction`, `visiblePackingItems`, `orderedPackingItems` are all derived computeds. No new persistent state.
- No backend changes in this phase.

## Acceptance Criteria

- The Packing panel header shows "{packed} of {total} packed" and a progress bar.
- Toggling "Hide packed" hides packed items and survives a hard refresh.
- Packed items appear below unpacked items (regardless of the filter).
- An all-packed list shows the celebratory empty state when "Hide packed" is on.
- Visuals look correct on every theme (`themes-plan` compatible).
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Per-traveler progress bars.
- Per-category progress bars.
- Drag-and-drop reordering.
- Bulk pack / clear all.
