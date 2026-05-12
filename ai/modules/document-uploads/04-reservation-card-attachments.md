# Phase 04 - Reservation Card Attachments

## Goal

Add an attachments section to every reservation card. The user can upload files directly from the reservation they belong to — auto-linking `reservation_id` — and see the files that are already linked without leaving the reservation. A small badge on the reservation header shows the attachment count so the section reveals itself naturally.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phase 03 (documents tab upload working, lightbox component available, `file_url` plumbing tested in the wild)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue:827-925` — current reservation card markup (one Card per reservation, with edit form vs read-only branches).
- `resources/js/pages/Trips/Show.vue` documents-tab logic from Phase 03 (upload form, lightbox component, row rendering).
- Trip serializer additions from Phase 01: `reservations[].document_count` and `documents[].reservation_id` + `file_url` + `preview_kind`.
- Phase 02 upload endpoint: accepts `reservation_id`, validated to belong to the same trip.

## Strategy

Add a small **AttachmentsSection** sub-block inside the read-only branch of every reservation card. The block:

1. Shows the count and a "Show" / "Hide" toggle (default collapsed if `document_count === 0`, default expanded when `document_count > 0` so the user lands on a populated state).
2. When expanded, lists the linked documents using the same row component the documents tab uses (image thumb / PDF icon / file icon).
3. Includes a smaller upload affordance — a single "Add attachment" button that opens a compact file picker. Uploaded files auto-fill `reservation_id`.

The upload affordance is intentionally simpler than the documents-tab drop zone (no drag-zone, no batch metadata form) because the reservation card is a denser surface and most reservation-bound uploads are 1–2 boarding passes or a hotel confirmation. The user picks files, optionally sets a title prefix, and submits. For larger batches the user still has the documents tab.

A new `<DocumentRow />` component is **not** required — the row markup from Phase 03 can be extracted into a `<DocumentRow>` SFC in `resources/js/components/DocumentRow.vue` if it stays readable inline. Either approach is acceptable; the implementer's call.

## Planned Changes

### 1. Reservation type update

`resources/js/pages/Trips/Show.vue`:

```ts
type Reservation = Record<string, any> & {
    id: number;
    title: string;
    type: string;
    // existing fields...
    document_count: number;
};

// New computed: docs grouped by reservation_id for fast lookup
const documentsByReservation = computed(() => {
    const map = new Map<number, TripDocument[]>();
    for (const doc of props.trip.documents) {
        if (doc.reservation_id !== null) {
            if (!map.has(doc.reservation_id)) map.set(doc.reservation_id, []);
            map.get(doc.reservation_id)!.push(doc);
        }
    }
    return map;
});
```

### 2. Reservation header badge

Above the reservation actions (Edit / Delete), add a small badge:

```vue
<span
    v-if="reservation.document_count > 0"
    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
    :title="`${reservation.document_count} attachment(s)`"
>
    <Paperclip class="h-3 w-3" />
    {{ reservation.document_count }}
</span>
```

### 3. AttachmentsSection block (in the reservation card read-only branch)

```vue
<div class="mt-3 rounded-md border border-dashed border-border p-3">
    <button
        type="button"
        class="flex w-full items-center justify-between text-sm font-medium"
        @click="toggleReservationAttachments(reservation.id)"
    >
        <span class="flex items-center gap-2">
            <Paperclip class="h-4 w-4" />
            Attachments
            <span class="text-xs text-muted-foreground">({{ reservation.document_count }})</span>
        </span>
        <ChevronDown
            class="h-4 w-4 transition-transform"
            :class="{ 'rotate-180': isReservationExpanded(reservation.id) }"
        />
    </button>

    <div v-if="isReservationExpanded(reservation.id)" class="mt-3 space-y-2">
        <!-- Linked document rows (re-use the documents-tab row markup or a DocumentRow component) -->
        <DocumentRow
            v-for="doc in documentsByReservation.get(reservation.id) ?? []"
            :key="doc.id"
            :document="doc"
            :trip-id="trip.id"
            :can-edit="trip.can_edit"
            :is-shared="isShared"
            compact
            @open-lightbox="openLightbox"
            @edit="startEdit('document', $event)"
            @destroy="destroyEntry(destroyDocument.url({ trip: trip.id, document: $event.id }), $event.title)"
        />

        <!-- Compact upload affordance -->
        <ReservationAttachmentUpload
            v-if="trip.can_edit"
            :trip-id="trip.id"
            :reservation-id="reservation.id"
            @uploaded="onAttachmentUploaded"
        />
    </div>
</div>
```

`<DocumentRow>` accepts a `compact` prop that tightens spacing for the reservation context (smaller thumbnail, single-line metadata). It emits `open-lightbox` / `edit` / `destroy` events so the parent can route them through the same handlers used by the documents tab.

### 4. ReservationAttachmentUpload component

A small inline component (kept in `resources/js/pages/Trips/Show.vue` or extracted to `resources/js/components/ReservationAttachmentUpload.vue` if it's longer than ~40 lines):

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { upload as uploadDocument } from '@/routes/trips/documents';

const props = defineProps<{ tripId: number; reservationId: number }>();
const emit = defineEmits<{ (e: 'uploaded'): void }>();

const fileInputEl = ref<HTMLInputElement | null>(null);
const form = useForm({
    files: [] as File[],
    title_prefix: '',
    document_type: 'attachment',
    expires_on: '',
    notes: '',
    reservation_id: props.reservationId,
});

const handleFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.files = Array.from(input.files ?? []);
};

const submit = () => {
    if (form.files.length === 0) return;
    form.post(uploadDocument.url(props.tripId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('files', 'title_prefix');
            if (fileInputEl.value) fileInputEl.value.value = '';
            emit('uploaded');
        },
    });
};
</script>

<template>
    <div class="rounded-md bg-muted/40 p-3 text-sm">
        <div class="flex items-center gap-2">
            <input
                ref="fileInputEl"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif"
                multiple
                class="text-xs"
                @change="handleFiles"
            />
        </div>
        <div v-if="form.files.length" class="mt-2 grid gap-2">
            <Input class="travel-touch" v-model="form.title_prefix" placeholder="Title prefix (optional)" />
            <Button type="button" class="travel-button-primary" :disabled="form.processing" @click="submit">
                Upload {{ form.files.length }} to this reservation
            </Button>
            <InputError :message="form.errors.files" />
        </div>
    </div>
</template>
```

`onSuccess` emits `uploaded` so the parent can scroll the newly linked row into view if desired — minor polish, not required.

### 5. Expanded-state persistence

```ts
const expandedReservations = ref(new Set<number>());

onMounted(() => {
    // Auto-expand every reservation that already has attachments.
    for (const r of props.trip.reservations) {
        if (r.document_count > 0) expandedReservations.value.add(r.id);
    }
});

const toggleReservationAttachments = (id: number) => {
    if (expandedReservations.value.has(id)) {
        expandedReservations.value.delete(id);
    } else {
        expandedReservations.value.add(id);
    }
};

const isReservationExpanded = (id: number) => expandedReservations.value.has(id);
```

No `localStorage` persistence in this phase — the page-local default ("auto-expand if has attachments") is a sensible reset on every visit and avoids storage clutter.

### 6. Deep-link compatibility

If `notification-deep-links` lands a user on a reservation card focused via `?focus=reservations&entity=Reservation-{id}`, the AttachmentsSection should be visible. The auto-expand-when-has-attachments rule covers this.

If the deep link targets a `PackingItem` … `App\Models\TripDocument` — `notification-deep-links` already routes those to the documents tab. No new resolver behavior needed.

### 7. Reservation card layout adjustments

The existing reservation card lays out title, provider, dates, address, notes, and last-edited line in a single column with status pill and edit/delete buttons on the right. The AttachmentsSection slots below `notes` and above `last_edited_by`, with margin-top to separate it.

On mobile (`<sm`), the section stays inside the same card and stacks naturally.

## State Management

- **Documents grouped by reservation:** computed `documentsByReservation` over `props.trip.documents`. Recomputed on every Inertia visit.
- **Expanded reservations:** `expandedReservations: ref<Set<number>>`. Auto-seeded on mount from `document_count`. No persistence.
- **Reservation badge:** read directly from `reservation.document_count` (server-computed in the serializer — always accurate).
- **Upload form:** per-section `useForm` instance owned by the `ReservationAttachmentUpload` component. Isolation prevents one card's upload from clobbering another.
- **Lightbox:** shared with the documents tab via the page-level `lightboxDocument` ref (Phase 03).

## Tests

This phase is primarily UI; rely on:

- Phase 02 backend test coverage of `reservation_id` validation and linking.
- A Pest Browser smoke (matching the verification approach used by `packing-attribution`):
  - Open a trip with one reservation that already has two attachments.
  - Assert the reservation card shows the count badge "2".
  - Assert the AttachmentsSection is auto-expanded.
  - Click the "Add attachment" file picker, attach a small fake PDF, submit, assert the section now lists three rows and the badge reads "3".
- `npm run types:check`, `npm run lint:check`, and `npm run build` as the required gates.

## Acceptance Criteria

- Every reservation card shows an attachment count badge when `document_count > 0`.
- Every reservation card has an AttachmentsSection that:
  - Auto-expands when there are existing attachments.
  - Lists linked documents using the same row affordances as the documents tab (thumbnail/lightbox for images, new-tab for PDFs, download for HEIC/text).
  - Includes a compact upload affordance gated by `trip.can_edit`.
- Uploading from a reservation card creates rows with `reservation_id` populated to that reservation; the badge increments and the section refreshes.
- Editing or deleting an attached document from the section uses the same handlers as the documents tab — no duplicated logic.
- The lightbox modal (from Phase 03) works identically when triggered from either surface.
- All existing trip planning tests still pass.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- "Attach existing document" — picking a previously-uploaded document and linking it to this reservation without re-uploading. (Locked out in the master plan based on the user's scoping decision.)
- Drag-from-list reordering of attachments within a reservation.
- Bulk-link / bulk-unlink actions.
- Per-reservation thumbnail strip (e.g. carousel) for many attachments. The simple vertical list is enough for the typical 1–3 documents per reservation.
- Auto-detection of which reservation a freshly uploaded file belongs to (e.g., parsing the PDF for a confirmation code). That belongs to the import-automation module, not here.
