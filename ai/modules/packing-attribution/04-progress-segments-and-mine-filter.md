# Phase 04 - Progress Segments And Mine Filter

## Goal

Make the new attribution data pay back in the header. Break the existing "X of Y packed" progress bar into per-assignee segments so a glance at the panel header tells each user how their share is going. Add a "Mine to pack" filter switch next to the existing "Hide packed" so the assigned user can collapse the list to just their items.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 03 (assignment UI shipped, so most rows have an `assigned_to_user_id`)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue:209-214` — current packing computeds: `packedCount`, `totalPackingCount`, and the existing `hidePacked`-aware `visiblePackingItems`.
- The progress header markup added by `packing-checkbox` Phase 04 (`packed of total` + thin `bg-primary` bar).
- The "Hide packed" Switch sitting in the header right side, with `localStorage` key `packing-hide-packed:{tripId}`.
- Phase 01 of this module: `props.trip.participants` and `item.assigned_to_user_id` are now available.

## Planned Changes

### 1. Per-assignee progress bar segments

Replace the single solid `bg-primary` bar with a segmented bar where each segment represents one assignee's share, filled to the assignee's packed ratio.

```ts
type AssigneeProgress = {
    id: number | null;            // null = unassigned bucket
    label: string;                 // first_name, traveler_name, or 'Unassigned'
    initials: string;
    packed: number;
    total: number;
};

const assigneeProgress = computed<AssigneeProgress[]>(() => {
    const buckets = new Map<string, AssigneeProgress>();

    for (const item of props.trip.packing_items) {
        const key = item.assigned_to_user_id !== null
            ? `user-${item.assigned_to_user_id}`
            : item.traveler_name?.trim()
                ? `name-${item.traveler_name.trim().toLowerCase()}`
                : 'unassigned';

        if (!buckets.has(key)) {
            const label = item.assigned_to?.first_name
                ?? item.traveler_name?.trim()
                ?? 'Unassigned';
            const initials = item.assigned_to?.initials
                ?? (item.traveler_name?.trim()
                    ? item.traveler_name.trim().slice(0, 2).toUpperCase()
                    : '—');
            buckets.set(key, { id: item.assigned_to_user_id, label, initials, packed: 0, total: 0 });
        }

        const bucket = buckets.get(key)!;
        bucket.total += 1;
        if (item.is_packed) bucket.packed += 1;
    }

    // Stable order: trip participants in trip.participants order, then traveler_name buckets alphabetical, then 'Unassigned'.
    const userOrder = new Map(
        props.trip.participants.map((p, idx) => [`user-${p.id}`, idx]),
    );

    return [...buckets.entries()]
        .sort((a, b) => {
            const [keyA] = a;
            const [keyB] = b;
            const userA = userOrder.get(keyA);
            const userB = userOrder.get(keyB);
            if (userA !== undefined && userB !== undefined) return userA - userB;
            if (userA !== undefined) return -1;
            if (userB !== undefined) return 1;
            if (keyA === 'unassigned') return 1;
            if (keyB === 'unassigned') return -1;
            return a[0].localeCompare(b[0]);
        })
        .map(([, value]) => value);
});
```

Render as a row of fixed-height segments, each width sized proportional to that bucket's `total / overall_total`. Inside each segment, fill the leading portion to `packed / total`:

```vue
<div class="flex w-full overflow-hidden rounded-full h-2 bg-muted">
    <div
        v-for="bucket in assigneeProgress"
        :key="`seg-${bucket.id ?? bucket.label}`"
        class="relative h-full border-r border-background last:border-r-0"
        :style="{ width: `${(bucket.total / totalPackingCount) * 100}%` }"
        :title="`${bucket.label}: ${bucket.packed}/${bucket.total}`"
    >
        <div
            class="absolute inset-y-0 left-0 bg-primary transition-all"
            :style="{ width: `${bucket.total === 0 ? 0 : (bucket.packed / bucket.total) * 100}%` }"
        />
    </div>
</div>
```

Below the bar, a flex row of small chip labels — one per bucket — showing initials and `packed / total`:

```
[AB] 4/8  ·  [SK] 2/5  ·  [Mom] 0/3  ·  [—] 1/2
```

The overall "X of Y packed" header line stays above the bar (already there from `packing-checkbox` Phase 04).

If `totalPackingCount === 0`, hide the segmented bar entirely (consistent with current behavior).

### 2. "Mine to pack" filter switch

Add a second `Switch` to the panel header, immediately to the left of the existing "Hide packed":

```vue
<div class="flex items-center gap-3">
    <label class="flex items-center gap-2 text-sm">
        <Switch v-model="mineOnly" />
        <span>Mine to pack</span>
    </label>
    <label class="flex items-center gap-2 text-sm">
        <Switch v-model="hidePacked" />
        <span>Hide packed</span>
    </label>
</div>
```

State:

```ts
const mineOnly = ref(false);
const mineOnlyKey = computed(() => `packing-mine-only:${trip.value.id}`);

onMounted(() => {
    mineOnly.value = localStorage.getItem(mineOnlyKey.value) === '1';
});

watch(mineOnly, (value) => {
    localStorage.setItem(mineOnlyKey.value, value ? '1' : '0');
});
```

### 3. Combine with existing filter

Update `visiblePackingItems` (already a computed on the page from `packing-checkbox` Phase 04) to apply both filters:

```ts
const visiblePackingItems = computed(() => {
    let list = props.trip.packing_items;

    if (mineOnly.value && currentUserId.value !== null) {
        list = list.filter((item) => item.assigned_to_user_id === currentUserId.value);
    }

    if (hidePacked.value) {
        list = list.filter((item) => !item.is_packed);
    }

    return list;
});
```

The existing `orderedPackingItems` computed (which sorts packed-to-bottom) wraps `visiblePackingItems` and continues to work unchanged.

### 4. Empty states

Three filter combinations need an empty state:

| `mineOnly` | `hidePacked` | All items satisfy filter? | Empty-state copy |
| --- | --- | --- | --- |
| off | off | n/a | (existing behavior — list shows everything; if zero items total, the existing "Add your first packing item" empty state shows) |
| on | off | no items assigned to current user | "Nothing assigned to you yet. Pick a row and assign it to your name to claim it." |
| off | on | every item packed | (existing) "Everything's packed. Bon voyage." |
| on | on | every item assigned to you is packed | "You're all packed. Nice work." |
| on | on | nothing assigned to you | (same copy as the `on / off / no items` row above — assignment, not packing, is the missing piece) |

Hide the segmented progress bar's segments-row when filters narrow the list, but keep the bar itself anchored to the **unfiltered** total — the user is filtering the list view, not the underlying progress.

### 5. Mobile considerations

The header switches stack vertically on `<sm` breakpoints; segmented bar remains full width.

The chip row below the bar wraps with `flex-wrap` so long traveler names don't push the layout sideways.

## State Management

- `mineOnly`: `ref<boolean>` + `localStorage` per trip, key `packing-mine-only:{tripId}`. Pattern mirrors the existing `hidePacked` state from `packing-checkbox` Phase 04.
- `assigneeProgress`: pure derived `computed` over `props.trip.packing_items` and `props.trip.participants`. No persistent state.
- All progress numbers always derive from the **unfiltered** `props.trip.packing_items` so the bar reflects the true picture even when the user is filtering for "Mine".
- No backend changes in this phase.

## Tests

- A type check (`npm run types:check`) is the required gate.
- `tests/Feature/PackingAttributionPayloadTest.php` covers the server payload needed by the progress buckets:
  - Add 4 items: 2 assigned to self, 2 assigned to collaborator, plus free-text/unassigned fallback coverage.
  - Assert the serializer exposes enough data for two linked-user buckets plus fallback buckets without extra requests.
- `npm run types:check`, `npm run lint:check`, and `npm run build` are the UI gates for the computed filters and progress rendering.

## Acceptance Criteria

- The Packing panel header shows a segmented progress bar with one segment per assignee bucket (linked user, free-text traveler, or unassigned).
- A chip row beneath the bar lists each bucket as `[initials] packed/total`.
- A "Mine to pack" Switch sits next to "Hide packed" in the panel header. Both persist per-trip in `localStorage`.
- The two filters compose correctly: `Mine + Hide packed` shows only unpacked items assigned to the current user.
- Empty states cover the new filter combinations with clear copy.
- The segmented bar reflects the **unfiltered** totals so progress remains accurate while filters are on.
- Visuals look correct across all five themes (`themes-plan` compatible).
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Per-category progress segments (only per-assignee in this phase).
- Bulk reassign actions.
- Sticky / pinned "Mine to pack" toggle across trips (per-trip persistence is intentional — the user's share differs by trip).
- Visual color-coding of assignees beyond the existing theme primary (deferred — would need a stable hash-to-color or palette scheme that respects theme tokens).
- Reordering by assignee.
