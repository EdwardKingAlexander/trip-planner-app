# Phase 03 - Frontend Documents Tab Upload

## Goal

Replace the documents tab's text-only list with a real attachment surface: drag-and-drop multi-file upload, image thumbnails inline with a click-to-enlarge lightbox, PDFs that open in a new tab, and an explicit download button on every row. Edit the existing rows to optionally link to a reservation after the fact.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phase 02 (upload + download endpoints shipped, Wayfinder regenerated)
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue:1282-1329` — current documents tab markup. Text-only.
- `resources/js/pages/Trips/Show.vue:32` — current document route imports.
- `resources/js/pages/Trips/Show.vue:219-225` — current `documentForm` shape. Needs no file field today.
- `resources/js/pages/Trips/Show.vue` `post()` / `patchEdit()` helpers (search around the form submissions) — confirm whether they handle `multipart/form-data` or need a sibling helper for the upload form.
- `resources/js/routes/trips/documents.ts` — after Phase 02, this exports `upload` and `file` helpers from Wayfinder.

## Strategy

Three components, all inside `Trips/Show.vue` so we stay within the existing single-page structure:

1. A **drop zone** at the top of the documents tab that doubles as a file picker. Drag in or click to pick. Selected files preview as a small list with sizes; submitting POSTs to `trips.documents.upload` with optional title prefix, document type, expires_on, and notes shared across the batch.
2. A **list of attachment rows** that replaces the current text-list. Each row renders by `preview_kind`: image thumbnail, PDF icon, generic icon, or text-note (no file). Image rows are clickable to open a **lightbox modal**.
3. A **lightbox modal** component (`PackingDocumentsLightbox.vue` or kept inline) for click-to-enlarge image preview. PDFs do not use the lightbox; they open via `target="_blank"`.

The existing "Add document note" form stays — text-only entries (e.g. "remember to bring the passport") are still useful for shared reminders without an attached file. It moves below the upload zone and is collapsed by default behind an "Add note instead" toggle to keep the visual hierarchy on uploads.

## Planned Changes

### 1. Imports and types

`resources/js/pages/Trips/Show.vue` top:

```ts
import {
    destroy as destroyDocument,
    store as storeDocument,
    update as updateDocument,
    upload as uploadDocument,
} from '@/routes/trips/documents';
```

Replace the `documents` type:

```ts
type TripDocument = {
    id: number;
    title: string;
    document_type: string;
    expires_on: string | null;
    notes: string | null;
    reservation_id: number | null;
    file_path: string | null;
    original_filename: string | null;
    mime_type: string | null;
    file_size_bytes: number | null;
    file_url: string | null;
    preview_kind: 'note' | 'pdf' | 'image' | 'image-opaque' | 'file';
    size_label: string | null;
    last_edited_by?: string | null;
};

// On the Trip type:
documents: TripDocument[];
```

### 2. Upload form state

```ts
const uploadForm = useForm({
    files: [] as File[],
    title_prefix: '',
    document_type: '',
    expires_on: '',
    notes: '',
    reservation_id: null as number | null,
});

const dropActive = ref(false);
const uploadProgress = ref<number | null>(null);

const submitUpload = () => {
    if (uploadForm.files.length === 0) return;

    uploadForm.post(uploadDocument.url(trip.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (e) => {
            uploadProgress.value = e?.percentage ?? null;
        },
        onSuccess: () => {
            uploadForm.reset('files', 'title_prefix', 'notes', 'expires_on', 'document_type', 'reservation_id');
            uploadProgress.value = null;
        },
        onError: () => {
            uploadProgress.value = null;
        },
    });
};
```

`forceFormData: true` makes Inertia send `multipart/form-data` even when no file is the very first field. `useForm`'s `onProgress` is the public hook for upload progress (see Inertia v3 docs).

### 3. Drop zone markup

```vue
<div
    class="travel-panel rounded-md border-2 border-dashed border-border p-6 text-center transition-colors"
    :class="dropActive ? 'border-primary bg-primary/5' : 'bg-muted/30'"
    @dragenter.prevent="dropActive = true"
    @dragover.prevent="dropActive = true"
    @dragleave.prevent="dropActive = false"
    @drop.prevent="handleDrop"
>
    <input
        ref="fileInputEl"
        type="file"
        accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif"
        multiple
        class="hidden"
        @change="handleFileInput"
    />
    <button type="button" class="travel-button-primary" @click="fileInputEl?.click()">
        Choose files
    </button>
    <p class="mt-2 text-sm text-muted-foreground">
        or drag and drop here. PDFs and images up to 15 MB each. Max 10 per upload.
    </p>
    <ul v-if="uploadForm.files.length" class="mt-4 space-y-1 text-sm text-left">
        <li v-for="(file, idx) in uploadForm.files" :key="idx" class="flex items-center justify-between rounded-md bg-background px-2 py-1">
            <span class="truncate">{{ file.name }}</span>
            <span class="text-xs text-muted-foreground">{{ formatBytes(file.size) }}</span>
            <button type="button" class="ml-2 text-xs text-destructive" @click="removeStagedFile(idx)">Remove</button>
        </li>
    </ul>
</div>

<form v-if="uploadForm.files.length" class="mt-3 grid gap-3" @submit.prevent="submitUpload">
    <Input class="travel-touch" v-model="uploadForm.title_prefix" placeholder="Title prefix (optional — defaults to filename)" />
    <Input class="travel-touch" v-model="uploadForm.document_type" placeholder="Type (e.g. boarding pass, passport)" />
    <Input class="travel-touch" v-model="uploadForm.expires_on" type="date" />
    <textarea v-model="uploadForm.notes" class="..." placeholder="Notes (applied to all)" />
    <Button type="submit" class="travel-button-primary" :disabled="uploadForm.processing">
        Upload {{ uploadForm.files.length }} file{{ uploadForm.files.length === 1 ? '' : 's' }}
    </Button>
    <div v-if="uploadProgress !== null" class="h-2 w-full rounded-full bg-muted">
        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${uploadProgress}%` }" />
    </div>
    <InputError :message="uploadForm.errors.files" />
    <InputError v-for="(err, i) in fileErrors" :key="i" :message="err" />
</form>
```

Where `handleDrop`, `handleFileInput`, `removeStagedFile`, and `formatBytes` are small helpers defined in the script block. Client-side validation rejects files exceeding 15 MB or with disallowed extensions before staging, with a per-row error message — these are belt-and-suspenders next to the server validation.

### 4. Row rendering

Replace the existing `<div v-for="document in trip.documents" ...>` block with a `preview_kind`-aware layout. Read-only branch:

```vue
<div
    v-for="document in trip.documents"
    :id="`document-${document.id}`"
    :key="document.id"
    tabindex="-1"
    class="flex items-start gap-3 rounded-md border border-border p-3 text-sm"
>
    <!-- Visual: thumbnail or icon -->
    <button
        v-if="document.preview_kind === 'image'"
        type="button"
        class="shrink-0"
        :aria-label="`Preview ${document.title}`"
        @click="openLightbox(document)"
    >
        <img
            :src="document.file_url"
            :alt="document.title"
            class="h-16 w-16 rounded-md object-cover ring-1 ring-border"
            loading="lazy"
        />
    </button>
    <a
        v-else-if="document.preview_kind === 'pdf'"
        :href="document.file_url"
        target="_blank"
        rel="noopener"
        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md bg-muted ring-1 ring-border"
        :aria-label="`Open ${document.title} in a new tab`"
    >
        <FileText class="h-8 w-8 text-primary" />
    </a>
    <a
        v-else-if="document.preview_kind === 'image-opaque' || document.preview_kind === 'file'"
        :href="document.file_url"
        download
        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md bg-muted ring-1 ring-border"
    >
        <FileQuestion class="h-8 w-8 text-muted-foreground" />
    </a>
    <div
        v-else
        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md bg-muted ring-1 ring-border"
        aria-hidden="true"
    >
        <StickyNote class="h-8 w-8 text-muted-foreground" />
    </div>

    <!-- Metadata block -->
    <div class="flex-1 min-w-0">
        <div class="font-medium truncate">{{ document.title }}</div>
        <div class="text-muted-foreground text-xs">
            {{ document.document_type }}
            <template v-if="document.expires_on"> · expires {{ formatDate(document.expires_on) }}</template>
            <template v-if="document.size_label"> · {{ document.size_label }}</template>
            <template v-if="document.original_filename && document.original_filename !== document.title">
                · {{ document.original_filename }}
            </template>
        </div>
        <div v-if="document.reservation_id" class="mt-1 text-xs">
            Linked to:
            <button
                type="button"
                class="underline underline-offset-2"
                @click="jumpToReservation(document.reservation_id)"
            >
                {{ reservationTitleFor(document.reservation_id) }}
            </button>
        </div>
        <p v-if="document.notes" class="mt-2 rounded-md bg-muted p-2 text-muted-foreground">{{ document.notes }}</p>
        <p v-if="isShared && document.last_edited_by" class="mt-2 text-xs italic text-muted-foreground">Last edited by {{ document.last_edited_by }}</p>
    </div>

    <!-- Actions -->
    <div class="flex shrink-0 items-center gap-2">
        <a v-if="document.file_url" :href="`${document.file_url}?download=1`" download class="travel-touch text-xs">
            <Download class="h-4 w-4" />
            <span class="sr-only">Download</span>
        </a>
        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('document', document)">Edit</Button>
        <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyDocument.url({ trip: trip.id, document: document.id }), document.title)">
            <Trash2 class="h-4 w-4" />
        </Button>
    </div>
</div>
```

The download URL uses `?download=1` as a hint to the file controller — a tiny addition to Phase 02 that toggles `Content-Disposition` to `attachment`. If left undone in Phase 02, the link still works (browsers download by default when the user clicks an anchor with `download`).

Icons (`FileText`, `FileQuestion`, `StickyNote`, `Download`, `Trash2`) come from `lucide-vue-next`, already used elsewhere in this file.

### 5. Edit form additions

The existing inline edit form gains:

```vue
<select v-model="editData.reservation_id" class="travel-input">
    <option :value="null">Not linked to a reservation</option>
    <option v-for="r in trip.reservations" :key="r.id" :value="r.id">
        {{ r.title }} ({{ r.type }})
    </option>
</select>
```

`updateDocument` already accepts `reservation_id` after Phase 01.

### 6. Lightbox modal

A simple modal local to this page (or extracted to `resources/js/components/DocumentLightbox.vue` if reused later):

```vue
<div
    v-if="lightboxDocument"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
    role="dialog"
    aria-modal="true"
    :aria-label="`Preview ${lightboxDocument.title}`"
    @click.self="closeLightbox"
    @keydown.esc="closeLightbox"
    tabindex="0"
    ref="lightboxEl"
>
    <button
        type="button"
        class="absolute right-4 top-4 text-white"
        @click="closeLightbox"
        aria-label="Close preview"
    >
        <X class="h-6 w-6" />
    </button>
    <img
        :src="lightboxDocument.file_url"
        :alt="lightboxDocument.title"
        class="max-h-full max-w-full rounded-md"
    />
    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-4 py-1 text-sm text-white">
        {{ lightboxDocument.title }} · {{ lightboxDocument.size_label }}
        <a :href="lightboxDocument.file_url" target="_blank" rel="noopener" class="ml-3 underline">Open full</a>
    </div>
</div>
```

Focus management: on open, focus the close button. On close, return focus to the row's thumbnail button.

### 7. Text-note collapse

Move the existing "Add document note" form (the textbox-only form) into a `<details>` block titled "Add a note instead (no file)":

```vue
<details class="travel-panel mt-4 rounded-md">
    <summary class="cursor-pointer px-4 py-2 text-sm font-medium">Add a note instead (no file)</summary>
    <div class="px-4 pb-4">
        <!-- existing storeDocument form -->
    </div>
</details>
```

This de-emphasizes the text-only flow without removing it — long-standing notes about "bring the passport" still belong here.

### 8. Mobile considerations

- The drop zone collapses to a tap-to-pick area on `<sm` — the dashed border + button still work, the "drag and drop" copy is replaced with "Tap to choose files" via a media query class.
- Each attachment row stacks vertically: thumbnail on top, metadata below, actions in a wrap row at the bottom.
- The lightbox modal uses `inset-0` and pinch-zoom-friendly image sizing.

## State Management

- **Staged-files state:** `uploadForm.files: File[]` and `uploadForm.title_prefix` etc. live in the existing `useForm` — Inertia owns reset and error state.
- **Upload progress:** `uploadProgress: ref<number | null>` driven by Inertia's `onProgress`. Reset on success or error.
- **Lightbox state:** `lightboxDocument: ref<TripDocument | null>`. Page-local; no global store.
- **Drop zone visual state:** `dropActive: ref<boolean>` toggled by dragenter/dragover/dragleave/drop handlers.
- **Reservation lookup for "linked to" labels:** `reservationTitleFor(id: number)` is a computed map built from `props.trip.reservations`. No caching across visits.

## Tests

This phase is primarily UI; rely on:

- Phase 02 backend tests (proves upload + download + linking work).
- `npm run types:check`, `npm run lint:check`, and `npm run build` as the required gates.
- Optionally a Pest Browser smoke (matching the verification-light pattern locked into the `packing-attribution` module's notes): hit `/trips/{id}`, click documents tab, assert the drop zone is visible and the title prefix input renders. Full upload flow is exercised via the manual matrix in Phase 05.

## Acceptance Criteria

- The documents tab opens to a drop zone above the attachment list; dragging files in or clicking opens the file picker.
- Up to 10 files can be staged at once; over-limit and oversized files are rejected client-side with a clear error.
- Submitting POSTs to `trips.documents.upload` with `multipart/form-data`, shows a progress bar, and on success the new rows render with thumbnails / icons / metadata.
- Image rows show a thumbnail and open a lightbox on click.
- PDF rows show a PDF icon and open in a new tab on click.
- HEIC and other "image-opaque" rows show a generic icon and download on click.
- Text-note rows still render (no file, just title + notes).
- Each row has a download button and (for editors) edit and delete buttons.
- The edit form lets the user link or unlink the row to a trip reservation.
- All existing trip planning tests still pass.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Reservation-card attachments section (Phase 04).
- Drag-to-reorder.
- Bulk select / bulk delete from the tab.
- Inline rename or in-place metadata edit without entering the edit form.
- Replace-file flow.
- Image rotation, cropping, or annotation.
