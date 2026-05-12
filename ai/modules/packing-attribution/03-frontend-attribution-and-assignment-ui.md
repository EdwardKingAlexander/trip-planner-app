# Phase 03 - Frontend Attribution And Assignment UI

## Goal

Surface the new attribution data in `Trips/Show.vue`. Every packing row shows who added it and who it's for. The add/edit form gains an assignee picker that defaults to the current user. The visual change is small but resolves the persistent "wait, who added this?" / "is this mine to pack?" ambiguity.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 01 (serializer fields) and Phase 02 (notification flow shipped, so the UI doesn't promise targeting that doesn't exist)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue:80` — `packing_items` type. Needs `added_by`, `assigned_to`, `assigned_to_user_id`.
- `resources/js/pages/Trips/Show.vue:157-163` — `packingForm`. Needs `assigned_to_user_id`.
- `resources/js/pages/Trips/Show.vue` packing read-only row markup (around lines 700+, post-`packing-checkbox` module). Currently shows quantity, label, traveler_name, last_edited_by, edit button, inline checkbox.
- `resources/js/pages/Trips/Show.vue` packing edit form. Currently has: traveler_name, category, label, quantity, is_packed, notes.
- `resources/js/components/ui/select` (assumed present — confirm before Phase 03 starts; if not, fall back to a native `<select>` styled with existing form classes).
- `usePage().props.auth.user` — current user for "Mine to pack" filter and form default. Confirm this is the property name in the existing layout's shared props.

## Planned Changes

### 1. Type updates (`resources/js/pages/Trips/Show.vue`)

```ts
type ParticipantSummary = {
    id: number;
    name: string;
    first_name: string;
    initials: string;
};

type PackingItem = Record<string, any> & {
    id: number;
    is_packed: boolean;
    sort_order?: number | null;
    label: string;
    quantity: number;
    traveler_name?: string | null;
    category: string;
    notes?: string | null;
    assigned_to_user_id: number | null;
    added_by: ParticipantSummary | null;
    assigned_to: ParticipantSummary | null;
};

// On the Trip type:
participants: Array<ParticipantSummary & { role: 'owner' | 'editor' | 'viewer' }>;
```

### 2. Form default

```ts
const currentUserId = computed(() => page.props.auth.user?.id ?? null);

const packingForm = useForm({
    traveler_name: '',
    category: 'clothes',
    label: '',
    quantity: 1,
    notes: '',
    assigned_to_user_id: currentUserId.value,  // default-to-self per user decision
});
```

If `currentUserId` is `null` for any reason (shouldn't be — packing actions require auth), fall back to leaving the field empty.

### 3. Assignee picker (in the add form and the edit form)

A dropdown labeled **"Who packs it?"** placed between `traveler_name` and `category`:

```vue
<select
    v-model="packingForm.assigned_to_user_id"
    class="travel-input"
    :disabled="!trip.can_edit"
>
    <option :value="null">Unassigned (use traveler name)</option>
    <option
        v-for="participant in trip.participants"
        :key="participant.id"
        :value="participant.id"
    >
        {{ participant.first_name }}{{ participant.id === currentUserId ? ' (me)' : '' }}
    </option>
</select>
```

UX rules:

- Default to current user on a fresh form (per user decision).
- When an account is selected, the existing `traveler_name` text input becomes optional and shows a helper label "Optional — only used when no account is assigned." It is **not hidden**, so the user can still write a label like "(travel pack)".
- When `Unassigned` is selected, `traveler_name` regains its current behavior — primary label for who carries the item.
- Edit form: pre-fill `assigned_to_user_id` from the loaded item; otherwise mirror the add form's behavior.

### 4. Read-only row attribution

The row gains two compact "chips" before or after the existing label, showing the adder and the assignee. The exact slot placement is left to the implementer's eye, but the row should not grow past one line on desktop for typical names.

Suggested layout (existing checkbox + label + actions remain; new chips slot in):

```
[✓] 3× Sunscreen · Toiletries     · added by [AB] · for [SK]    [Edit]
                                       notes (existing) below
```

Implementation:

```vue
<span class="text-muted-foreground text-xs flex items-center gap-1">
    <span>added by</span>
    <Avatar v-if="item.added_by" :initials="item.added_by.initials" :title="item.added_by.name" />
    <span v-else>—</span>
    <span class="ml-2">for</span>
    <template v-if="item.assigned_to">
        <Avatar :initials="item.assigned_to.initials" :title="item.assigned_to.name" />
    </template>
    <template v-else-if="item.traveler_name">
        <span :title="item.traveler_name">{{ item.traveler_name }}</span>
    </template>
    <span v-else>anyone</span>
</span>
```

`Avatar` is a small inline component used by this module — either reuse an existing one if `resources/js/components/ui/avatar` exists, or add a tiny inline component:

```vue
<!-- resources/js/components/PackingAttributionAvatar.vue -->
<template>
    <span
        class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary/15 text-[10px] font-medium text-primary"
        :title="title"
        :aria-label="title"
    >
        {{ initials }}
    </span>
</template>
```

Color uses theme tokens (`bg-primary/15`, `text-primary`) so all five themes render correctly.

Hover/long-press reveals the full name via the `title` attribute. Screen readers get the same via `aria-label`.

### 5. Highlighting "this is mine to pack"

When `item.assigned_to_user_id === currentUserId`, the assignee chip gets an extra ring (`ring-1 ring-primary`) so the user can see at a glance which items are theirs without needing the "Mine to pack" filter (Phase 04). This is purely visual — no filter behavior changes here.

### 6. Edit form parity

The existing edit form gains the same assignee picker. The form already submits to `updatePacking`, which (Phase 01) now accepts `assigned_to_user_id`. No new endpoint, no Wayfinder regen needed.

### 7. Wayfinder regen

If the validated payload type that Wayfinder generates for the form helpers includes `assigned_to_user_id`, run `php artisan wayfinder:generate --with-form --no-interaction` once after Phase 01 lands the validation rule. Confirm `resources/js/actions/App/Http/Controllers/TripPlanningController.ts` reflects the new field.

## State Management

- **Form state:** `packingForm.assigned_to_user_id` is a single field on the existing `useForm`. No new global state.
- **Row attribution display:** purely derived from `props.trip.packing_items[i].added_by` / `.assigned_to` / `.traveler_name`. No client-side caching.
- **Current user id:** read from `usePage().props.auth.user.id` once into a `computed`. Used by the form default and the "is mine?" highlight.
- **Optimistic updates:** none in this phase. Assignment changes go through the existing edit-form flow which does a full Inertia visit.
- **Participants list:** read fresh from `props.trip.participants` on every visit. Never cached separately.

## Tests

This phase is primarily UI; rely on:

- Phase 01 backend validation tests (proves the field is accepted and rejected correctly).
- Phase 02 notification tests (proves the targeted flow works once the assignment is set).
- Feature/Inertia payload tests in `tests/Feature/PackingAttributionPayloadTest.php`:
  - Loads `/trips/{id}` as the trip owner and asserts `participants`, `added_by`, `assigned_to`, and `assigned_to_user_id` are present in the page payload.
  - Creates a packing item with the assignee default contract represented server-side, then updates the item to a collaborator and asserts the serializer reflects the new assignee.
  - Asserts free-text `traveler_name` still serializes and renders as the fallback path when no account is assigned.
- `npm run types:check` confirms the Vue assignment UI compiles cleanly.

A type check (`npm run types:check`) is the required gate.

## Acceptance Criteria

- `Trips/Show.vue` types include `added_by`, `assigned_to`, `assigned_to_user_id`, and `participants`.
- The add form's assignee field defaults to the current user.
- The edit form preloads the assignee and saves changes through the existing endpoint.
- Every read-only packing row shows the adder chip and the assignee/traveler-name chip; rows assigned to the current user have a visible "this is mine" emphasis.
- `traveler_name` continues to render as the "for" label when no account is assigned.
- The picker offers the trip owner plus all linked-account collaborators; the picker is disabled when `!trip.can_edit`.
- All existing Packing tests still pass.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- One-click reassignment from the read-only row (deferred — the edit form is the only assignment surface in this module).
- Per-assignee progress segments or "Mine to pack" filter (Phase 04).
- Avatars sourced from a profile image — initials only for now.
- A dedicated assignee-aware activity feed entry (`packing.reassigned`) — covered by the umbrella `packing.updated` event.
