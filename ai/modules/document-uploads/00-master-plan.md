# Document Uploads Master Plan

## Goal

Let users actually upload travel documents — PDFs, boarding passes, hotel confirmations, passport scans, photos of physical receipts — into a trip, view them inline (images) or in a new tab (PDFs), and attach them to the specific reservation they belong to. Today the documents tab is a text-notes list and the reservation card has no document surface at all.

## Status

- Status: `verified`
- State file: `ai/state/document-uploads.json`
- Last updated: 2026-05-12

## Problem

The data model has been ready for files since day one and never got used:

- `trip_documents.file_path` and `trip_documents.reservation_id` exist as nullable columns (`database/migrations/2026_05_04_000001_create_trip_management_tables.php:176-186`) but the controller (`TripPlanningController::validatedDocument`, `app/Http/Controllers/TripPlanningController.php:408-416`) ignores both — it only accepts `title`, `document_type`, `expires_on`, and `notes`.
- The documents UI (`resources/js/pages/Trips/Show.vue:1282-1329`) renders documents as text rows with no file picker, no preview, and no download button.
- The reservations UI (`resources/js/pages/Trips/Show.vue:827-925`) doesn't surface attached documents at all — a user looking at a flight booking can't see (or upload) the boarding pass attached to it.
- No file-upload code path exists anywhere in the application yet. The only "upload" is the text-paste import flow. `config/filesystems.php` defaults to the `local` driver writing to `storage/app/private`.

The result: when planning a real trip, users have to keep boarding passes in their email and passport scans in their phone gallery. The "Documents" tab is dead weight.

## Strategy

Five threads, each independently shippable:

1. **Schema extension, not replacement.** The existing `trip_documents` row holds one file. Add three columns (`original_filename`, `mime_type`, `file_size_bytes`) so the UI can render filename, icon, and size without re-reading the file. Keep `file_path` as the storage-relative path. Keep `reservation_id` exactly as it is.
2. **A focused upload endpoint** that accepts multipart, validates type + size, writes the file to a trip-scoped path on the `local` disk, and creates the `trip_document` row. Multi-file selections create one row per file. The existing `document` (create) endpoint stays for text-only document records (the user can still add a "remember to bring X" reminder without a file).
3. **An authenticated download/preview endpoint** that streams the file with `Content-Disposition: inline` so images render in `<img>` tags and PDFs open in a new browser tab. Every read is gated through the trip view policy — no public URLs, no signed-URL workaround.
4. **A documents-tab UI** that supports drag-and-drop multi-file upload, shows image thumbnails inline with a lightbox click-to-enlarge, shows PDFs as iconed rows with "Open" + "Download", and keeps the existing notes/expires fields. The edit form gains a "Link to reservation" dropdown for after-the-fact linking.
5. **A reservation-card attachments section** with its own upload button that creates document rows pre-linked to that reservation. A small badge on each reservation surfaces "n attachment(s)" so the user knows what's attached without expanding.

Activity events and notification fan-out keep working as they do today: `document.created` / `document.updated` / `document.deleted` continue to broadcast through `TripCollaborationEventService::record()`. A new `document.uploaded` event type fires when a file is attached (vs a text-only note), so the activity feed and inbox can read naturally ("Alex uploaded boarding-pass.pdf").

State management mirrors existing patterns: server is the source of truth, optimistic UI is only used where a request stays under ~200 ms (delete confirmations), uploads show a real progress indicator backed by Inertia's `useForm` `progress` callback.

## Phases

1. [Data Model And Upload Contract](01-data-model-and-upload-contract.md)
2. [Backend Upload And Download](02-backend-upload-and-download.md)
3. [Frontend Documents Tab Upload](03-frontend-documents-tab-upload.md)
4. [Reservation Card Attachments](04-reservation-card-attachments.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Phase 01 freezes the migration shape, file constraints, and endpoint contract so everything else has a stable target. Phase 02 ships the upload/download endpoints with full tests so the frontend has an authority to call. Phase 03 wires the documents-tab UI (the primary surface) end-to-end. Phase 04 layers on the reservation-card attachments, reusing the upload endpoint with `reservation_id` pre-filled. Phase 05 is the verification gate.

## File Constraints (frozen)

| Setting | Value |
| --- | --- |
| Allowed MIME types | `application/pdf`, `image/jpeg`, `image/png`, `image/webp`, `image/heic`, `image/heif` |
| Allowed extensions | `pdf`, `jpg`, `jpeg`, `png`, `webp`, `heic`, `heif` |
| Maximum file size | 15 MB (15 × 1024 × 1024 bytes) |
| Maximum files per upload request | 10 (so one drag-drop can't create 200 rows) |
| Storage disk | `local` (private, `storage/app/private`) |
| Storage path template | `trips/{trip_id}/documents/{document_id}/{sanitized_filename}` |
| Filename sanitization | lowercase, strip non `[a-z0-9._-]`, collapse repeats, then prefix with a short random hash to avoid collisions when the same name is uploaded twice |
| Original filename column | preserved verbatim in `original_filename` for display |

HEIC/HEIF support: accepted for upload (iPhone users save these by default) but **not previewed inline** — the row renders a generic "image" icon and clicking it downloads the file. Documented for users so the behavior is not surprising.

## Acceptance Criteria

- A signed-in user with `trip.update` can upload one or more files from the documents tab; each file becomes a `trip_documents` row with `file_path`, `original_filename`, `mime_type`, and `file_size_bytes` populated.
- A signed-in user with `trip.update` can upload one or more files directly from a reservation card; each upload auto-fills `reservation_id` on the new row.
- The documents tab renders each row with the appropriate visual: image thumbnail (jpg/png/webp), generic image icon (heic/heif), PDF icon (pdf), or generic file icon (text-only entries with no file).
- Clicking an image thumbnail opens a lightbox modal showing the full-size image. Clicking a PDF row opens the file in a new browser tab with `Content-Disposition: inline`. Every row has an explicit "Download" button.
- The reservation card surfaces a count of attached files and an inline list of those files, each with the same preview/download affordances as the documents tab.
- The edit form on a document row offers a "Link to reservation" dropdown (any reservation on this trip plus "(none)") for after-the-fact linking.
- File access is gated through the trip view policy: a non-participant cannot fetch a file even if they know the URL.
- Deleting a document row deletes the underlying file from disk in the same transaction (or rolls back the row delete if the file delete fails).
- File constraints are enforced on the server: too-large files, disallowed MIME types, and disallowed extensions all return 422 with field-level errors.
- Activity events fire: `document.uploaded` when a file is attached (vs `document.created` for text-only entries), `document.updated` when metadata changes, `document.deleted` on delete. Targeted notifications follow the existing fan-out — no new notification class required.
- All standard checks pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`, and `php artisan wayfinder:generate --with-form --no-interaction`.

## Out Of Scope

- Cloud storage (S3, R2, etc.) — `local` disk only. The disk is selected via `config('filesystems.default')` so a future migration to S3 is a single config change.
- OCR / auto-extraction of confirmation numbers from PDFs or boarding-pass images.
- Server-side thumbnail generation. Image rows show the full file resized via CSS. Adding GD/Imagick-based thumbnail jobs is a follow-up if bandwidth becomes an issue.
- Inline PDF viewer (embedded iframe). PDFs open in a new tab — simpler and avoids mobile rendering issues.
- Multi-file storage per document record (a separate attachments table). One file per row keeps the existing schema clean; multi-file uploads create N rows.
- Reservation-picker dropdown in the documents-tab upload form. After-the-fact linking is via the existing edit form's new dropdown only.
- "Attach existing document" flow from a reservation card (re-using a previously uploaded file). Each reservation uploads its own; if the same file is needed for two reservations, the user re-uploads or edits the document row to switch its `reservation_id`.
- Versioning / replace-file. Editing a document either leaves the file as-is or deletes and re-uploads; there's no "upload new version" history.
- Public sharing or expiring share links.
- Antivirus / malware scanning on upload.
- File compression or PDF flattening.
- ICS/Email-import attachment ingestion (the existing import paths stay text-only for now).
