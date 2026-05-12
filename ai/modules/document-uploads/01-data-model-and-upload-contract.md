# Phase 01 - Data Model And Upload Contract

## Goal

Lock the migration shape, the storage path strategy, the validation rules, and the endpoint contract for upload, download, and update so Phases 02–04 can build against a frozen agreement.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: none
- Blocker: none

## Audit Inputs

- `database/migrations/2026_05_04_000001_create_trip_management_tables.php:176-186` — current `trip_documents` schema. `file_path` is `string nullable`; `reservation_id` is `foreignId nullable` with `nullOnDelete`. No filename, mime, or size columns.
- `app/Models/TripDocument.php:13` — `$fillable` already includes `reservation_id` and `file_path`; missing the three new columns.
- `app/Http/Controllers/TripPlanningController.php:251-302` — current `document` / `updateDocument` / `destroyDocument` actions accept text fields only.
- `app/Http/Controllers/TripPlanningController.php:408-416` — current `validatedDocument()` permits `title`, `document_type`, `expires_on`, `notes` only.
- `routes/web.php:49-51` — current document routes: `store`, `update`, `destroy`. No upload route, no download route.
- `config/filesystems.php` — default disk is `local` writing to `storage/app/private`. The `public` disk exists but is not the default; we keep using `local` so files are gated through a controller and never exposed via a static path.
- `app/Http/Controllers/TripController.php:77,237-240` — trip serializer eager loads `documents.updatedBy` and attaches `last_edited_by`. Needs to also serialize the new file fields.

## Migration (frozen)

New migration file: `database/migrations/<TIMESTAMP>_add_file_metadata_to_trip_documents.php`.

```php
Schema::table('trip_documents', function (Blueprint $table) {
    $table->string('original_filename')->nullable()->after('file_path');
    $table->string('mime_type', 120)->nullable()->after('original_filename');
    $table->unsignedBigInteger('file_size_bytes')->nullable()->after('mime_type');
});
```

`down()` drops the three new columns. Existing rows keep `null` values for all three; the UI handles `null` gracefully ("text note" rendering).

`reservation_id` and `file_path` are **not** touched — they already exist and behave correctly.

## Model (frozen)

`app/Models/TripDocument.php`:

- Add `'original_filename'`, `'mime_type'`, `'file_size_bytes'` to `$fillable`.
- Cast `'file_size_bytes' => 'integer'`.
- Add a relation:
  ```php
  public function reservation(): BelongsTo
  {
      return $this->belongsTo(Reservation::class);
  }
  ```
- Add a `deleting` model event hook (registered in the model's `booted()` method) that removes the file from disk before the row is deleted:
  ```php
  protected static function booted(): void
  {
      static::deleting(function (TripDocument $document) {
          if ($document->file_path) {
              Storage::disk('local')->delete($document->file_path);
          }
      });
  }
  ```
  Documented limitation: this fires on individual model deletes only. A trip force-delete cascades at the DB level and bypasses this hook — handled by a separate `Trip::deleting` hook documented in Phase 02.

- Add a derived accessor for the kind of preview to render, used by the frontend:
  ```php
  public function previewKind(): string
  {
      if ($this->file_path === null) return 'note';
      if ($this->mime_type === 'application/pdf') return 'pdf';
      if (in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) return 'image';
      if (in_array($this->mime_type, ['image/heic', 'image/heif'], true)) return 'image-opaque';
      return 'file';
  }
  ```
  `image-opaque` distinguishes images browsers can't render inline (HEIC) — the frontend treats those as download-only.

## Storage Path Strategy (frozen)

Files live at:

```
storage/app/private/trips/{trip_id}/documents/{document_id}/{hash}-{sanitized_filename}
```

- `{hash}` is a short random string (e.g. `Str::lower(Str::random(8))`) generated at upload time to avoid collisions when the same filename is uploaded twice.
- `{sanitized_filename}` is the original filename downcased, with characters outside `[a-z0-9._-]` replaced by `-` and runs collapsed (`Str::slug` adapted to preserve `.` for the extension).
- The directory `trips/{trip_id}/documents/{document_id}/` holds exactly one file per document row in this module. Reserved for a future multi-attachment migration if it ever happens.

Path generation lives in a single helper (`app/Services/TripDocumentStorage.php`, introduced in Phase 02) so the controller stays thin and tests can assert path shape.

## Validation Rules (frozen)

A new request shape for the upload endpoint, distinct from the existing `document` create endpoint which keeps accepting text-only data.

### Upload endpoint validation

```php
$request->validate([
    'files'           => ['required', 'array', 'min:1', 'max:10'],
    'files.*'         => ['file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif'],
    'title_prefix'    => ['nullable', 'string', 'max:120'],
    'document_type'   => ['nullable', 'string', 'max:80'],
    'expires_on'      => ['nullable', 'date'],
    'notes'           => ['nullable', 'string', 'max:1000'],
    'reservation_id'  => [
        'nullable',
        'integer',
        Rule::exists('reservations', 'id')->where(fn ($q) => $q->where('trip_id', $trip->id)),
    ],
]);
```

Notes on the validation contract:

- `max:15360` is in KB (Laravel's `max` for file rules), which equals 15 MB.
- `mimes:` enforces extension; combined with the request's reported MIME type Laravel also confirms the file's actual MIME via finfo.
- `reservation_id` is server-validated to belong to the same trip — the frontend always sends a vetted id (from `props.trip.reservations`) but server enforcement is required so a malicious request can't link a file to a reservation on another trip.
- `title_prefix` is optional; for each uploaded file, the resulting row's `title` is `{title_prefix ?: original_filename_without_extension}` (per file, so a 3-file upload with prefix "Boarding Pass" yields three rows titled "Boarding Pass", "Boarding Pass (2)", "Boarding Pass (3)" — or just three rows titled by their filenames if no prefix is given).

### Existing `document` (text-note) create endpoint validation

Stays exactly as it is (`validatedDocument()`), plus one new field:

```php
'reservation_id' => [
    'nullable',
    'integer',
    Rule::exists('reservations', 'id')->where(fn ($q) => $q->where('trip_id', $trip->id)),
],
```

This lets the text-note flow link a note to a reservation too (for consistency with the file flow).

### Existing `updateDocument` validation

Same rule set as the text-note create, plus the new `reservation_id` field. **No file change through the update endpoint** — replacing a file means delete + re-upload.

## Endpoint Contract (frozen)

Three routes total. Two new, one unchanged.

| Method | URL | Name | Purpose |
| --- | --- | --- | --- |
| `POST` | `/trips/{trip}/documents/upload` | `trips.documents.upload` | Multipart upload, creates N rows |
| `GET` | `/trips/{trip}/documents/{document}/file` | `trips.documents.file` | Stream file inline, gated by trip view policy |
| `POST` | `/trips/{trip}/documents` | `trips.documents.store` (existing) | Text-note create, gains optional `reservation_id` |
| `PATCH` | `/trips/{trip}/documents/{document}` | `trips.documents.update` (existing) | Update metadata + optional `reservation_id` |
| `DELETE` | `/trips/{trip}/documents/{document}` | `trips.documents.destroy` (existing) | Delete row + underlying file |

Response shapes:

- `upload`: `302 back()` with success flash on success. `422` with field-level errors on validation. `403` if not editor.
- `file`: `200` with `Content-Type` matching `mime_type`, `Content-Disposition: inline; filename="{original_filename}"`. `403` if not a trip participant. `404` if document or file missing.
- Existing `store` / `update` / `destroy`: unchanged response shapes.

The download endpoint name `trips.documents.file` (not `download`) is intentional — the same URL serves images for `<img src>` and PDFs for new-tab open. A future `?download=1` query param could force `Content-Disposition: attachment`, deferred to Phase 02 implementation.

## Trip Serializer Additions (frozen)

`TripController::tripDetail()` `'documents'` array gains the new file metadata and a precomputed `file_url`:

```php
'documents' => $trip->documents->map(fn ($document) => [
    ...$document->toArray(),
    'last_edited_by' => $this->editorName($document),
    'file_url' => $document->file_path
        ? route('trips.documents.file', ['trip' => $trip->id, 'document' => $document->id])
        : null,
    'preview_kind' => $document->previewKind(),
    'size_label' => $document->file_size_bytes
        ? $this->humanFileSize($document->file_size_bytes)
        : null,
]),
```

Add a `humanFileSize(int $bytes): string` helper on `TripController` (or a small `app/Support/FileSize.php` helper if it's reused elsewhere) — outputs strings like `2.3 MB`, `412 KB`.

The reservation rows on the trip serializer gain a precomputed count of attached documents so the reservation card can show a badge without a follow-up query:

```php
'reservations' => $trip->reservations->map(fn ($reservation) => [
    ...$reservation->toArray(),
    'last_edited_by' => $this->editorName($reservation),
    'document_count' => $trip->documents->where('reservation_id', $reservation->id)->count(),
]),
```

(The `where` filter on a preloaded collection is in-memory — no extra query — because `documents` is already eager-loaded above.)

## Frontend Type Additions

`resources/js/pages/Trips/Show.vue` `documents` and `reservations` type entries gain:

```ts
type TripDocument = Record<string, any> & {
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
};

// Reservation:
document_count: number;
```

## Open Questions To Resolve Before Phase 02

- Confirm `image-opaque` as the preview kind label for HEIC/HEIF (vs `image-download-only`).
- Confirm the next available migration timestamp slot.
- Confirm `humanFileSize` lives on `TripController` for now vs being promoted to a `Support` helper.
- Confirm storage path uses `{document_id}` subdirectory (chosen) vs a flat path with a hash prefix.

Record answers in `ai/state/document-uploads.json` `decisions[]` before Phase 02.

## State Management

- Server is the source of truth for everything: file existence on disk and metadata in `trip_documents`.
- The frontend never caches file URLs — they are recomputed by the serializer on every visit. URLs are stable because they're keyed by `document_id`, but we treat them as derived data.
- Files on disk are owned by the model lifecycle: the model `deleting` hook removes the file. No filesystem-only state lives outside the model.
- Frontend upload state lives in a transient `useForm` per upload session — no global store, no Pinia.

## Acceptance Criteria

- Migration is written and runs cleanly forward and backward against a fresh DB.
- `TripDocument` model exposes the new fillable fields, casts, relation, deleting hook, and `previewKind()`.
- `validatedDocument()` (existing) accepts `reservation_id` and validates it against trip reservations.
- A new validation method for uploads accepts `files[]`, `reservation_id`, optional metadata, and enforces the file constraints.
- Trip serializer attaches `file_url`, `preview_kind`, `size_label` on each document and `document_count` on each reservation.
- Frontend `TripDocument` TypeScript shape is extended.
- Open questions are answered in the state JSON.

## Out Of Scope

- Wiring the upload controller (Phase 02).
- Building the documents-tab UI (Phase 03).
- Reservation-card attachments section (Phase 04).
- Thumbnail generation, OCR, antivirus scanning, cloud storage migration.
