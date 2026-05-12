# Phase 05 - Verification And Release

## Goal

Prove uploads write files to disk, downloads stream them back gated by the trip policy, the documents tab and the reservation card both work end-to-end across image and PDF cases, deletes clean up disk state, and the release gates pass.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phases 01–04
- Blocker: none

## Verification Matrix

For an authenticated user with edit rights on a trip that has at least one reservation:

1. **Documents tab — single PDF upload:** drag one PDF onto the drop zone. Confirm it appears in the staged list with its size. Submit. The row appears with the PDF icon. Click — PDF opens in a new tab. Click Download — file downloads with the original filename.
2. **Documents tab — multi-file upload:** drag three images of mixed types (jpg, png, heic). Set title prefix "Boarding Pass". Submit. Confirm three rows render: two image thumbnails ("Boarding Pass (1)", "Boarding Pass (2)") and one HEIC row with the generic icon ("Boarding Pass (3)").
3. **Image lightbox:** click an image thumbnail. Lightbox opens, full image visible. Press Esc — lightbox closes, focus returns to the thumbnail button.
4. **HEIC handling:** click the HEIC row's icon. File downloads (browser cannot inline it). No console errors.
5. **PDF inline:** click a PDF row. Opens in a new browser tab, renders in the built-in PDF viewer.
6. **Oversize rejection (client):** stage a file > 15 MB. Inline error appears below the row; the file is not added to the staged list.
7. **Oversize rejection (server):** bypass the client check by selecting via DevTools or curl and POSTing a 16 MB file. Server responds 422 with a field error on `files.0`. No row created. No file on disk.
8. **Disallowed type rejection:** try to upload a `.zip` — client rejects, server also rejects if forced.
9. **Reservation card upload:** open a reservation card. Click the file input inside the AttachmentsSection. Select a PDF. Submit. The card's badge increments and the new row appears in the section. The same row is visible in the documents tab linked to that reservation.
10. **Reservation card auto-expand:** open the trip — every reservation that already has attachments shows its section pre-expanded.
11. **Reservation badge accuracy:** delete an attachment from a reservation. Badge count decrements without a full page reload (preserveScroll on the Inertia visit).
12. **Edit form linking:** open the edit form on a document row in the documents tab. Use the "Link to reservation" dropdown to attach to a reservation. Save. The badge appears on the reservation card.
13. **Edit form unlinking:** open the edit form on a linked document. Set the dropdown to "Not linked to a reservation". Save. The badge decrements and the document still exists in the documents tab.
14. **Viewer role read-only:** as a viewer-role collaborator, confirm:
    - The drop zone and AttachmentsSection upload form are hidden or disabled.
    - The download button works.
    - The lightbox works.
    - Delete and Edit buttons are not shown.
15. **Non-participant denied:** sign out, sign in as a user not on the trip. Visit `/trips/{id}/documents/{document}/file` directly. Server returns 403. No bytes leaked.
16. **Deleted file disk cleanup:** upload, then delete the row. Confirm the file is gone from `storage/app/private/trips/{trip_id}/documents/{document_id}/`. The directory itself is left in place — that's fine; a follow-up cleanup is not required.
17. **Trip soft-delete preserves files:** delete the trip (soft). Confirm files remain on disk. Restore the trip. Confirm documents are still listed and downloadable.
18. **Trip force-delete clears files:** force-delete a trip. Confirm `trips/{trip_id}/` directory is empty or removed.
19. **Activity feed:** open the activity feed. Confirm `document.uploaded` events appear with the file size in metadata. Confirm `document.created` still appears for text-note creations (no file).
20. **Notification fan-out:** as a second participant (different browser), confirm a "Alex uploaded boarding-pass.pdf" notification appears with the right summary. Clicking it opens the documents tab with the row highlighted (covered by `notification-deep-links`).
21. **Concurrent uploads:** open two browser tabs as the same user. Start an upload in one. Start a different upload in the second tab a second later. Confirm both succeed independently. No file path collisions.
22. **Theme audit:** cycle through all five themes. Confirm thumbnails, drop zone borders, lightbox backdrop, and badge contrast remain legible on every theme.

## Programmatic Verification

All must pass:

- `php artisan migrate:fresh --seed` then a round-trip: `php artisan migrate:rollback --step=1` followed by `php artisan migrate`. Confirms the new migration is reversible.
- `php artisan test --compact` — full Pest suite including:
  - `TripDocumentUploadTest` (Phase 02)
  - `TripDocumentDeleteTest` (Phase 02)
  - `TripDocumentLinkTest` (Phase 02)
  - `TripDocumentSerializerTest` (Phase 02)
  - existing `TripManagementTest`, `PackingTogglePackedTest`, `PackingAttributionNotificationTest`, etc. — must remain green.
- `npm run lint:check`
- `npm run build` — Wayfinder regen for the new endpoint helpers.
- `npm run types:check` (after build).
- `vendor/bin/pint --dirty --format agent`

## Theme + Accessibility Audit

- Cycle the page through all five themes from `themes-plan` — confirm:
  - Drop zone dashed border, hover/drag-active highlight, and primary button remain legible.
  - Attachment count badge contrast meets WCAG AA against the reservation card background.
  - Thumbnail rings and lightbox controls stay visible.
- Tab through the documents tab:
  - Drop zone "Choose files" button is reachable.
  - Each row's thumbnail button is reachable; pressing Enter opens the lightbox (for images) or follows the link (for PDFs).
  - Edit / Delete / Download buttons are reachable.
- Tab through a reservation card:
  - Attachments toggle is reachable and announces "Attachments, 2" via the `aria-label`/title.
  - File input is reachable.
- Screen-reader sanity check: thumbnails have `alt="{document title}"`, the lightbox region is `role="dialog"` with `aria-modal="true"`.

## Manual Verification Script

1. Sign in as the trip owner. Open `/trips/{id}` Documents tab.
2. Run verification matrix items 1–8 in order.
3. Switch to the Reservations tab. Run items 9–13.
4. Sign out. Sign in as a viewer-role collaborator. Run item 14.
5. Sign out. Sign in as a non-participant. Run item 15.
6. Back as owner, run delete / cleanup items 16–18.
7. Run items 19–22 as a final pass.

## Regression Watch List

- The existing text-note "Add document note" form must still work end-to-end (now inside the `<details>` block).
- `document.created` events still fire for text-only entries (separate from `document.uploaded`).
- `NotificationDeepLinkResolver` continues to route both `document.created` and `document.uploaded` to the document anchor on the trip show page.
- Trip JSON export (`TripExportService::jsonPayload`) continues to serialize documents (the new file metadata columns are part of `toArray()` automatically — verify the export still parses cleanly).
- `live-trip-collaboration` realtime fan-out still receives the new event types and refreshes the trip view.
- Existing reservations without attached documents render exactly as they do today (no extra section unless `document_count > 0` triggers auto-expand, in which case the section is empty/collapsed).
- `last_edited_by` continues to populate via `TracksAuthor` on uploads (covered by Phase 02 tests).
- The Pint formatter remains clean against the new controller and service files.

## Handoff

- Update `ai/state/document-uploads.json` with `status: verified`, fill `verification[]` with exact commands and results.
- `handoff.summary` covers:
  - Where the upload + download endpoints live (`app/Http/Controllers/TripDocumentController.php`).
  - Where the storage helper lives (`app/Services/TripDocumentStorage.php`) and how to swap the disk later (single `Storage::disk(...)` call).
  - Where the documents tab UI lives (drop zone + lightbox in `resources/js/pages/Trips/Show.vue`, optionally an extracted `<DocumentRow>`).
  - Where the reservation-card attachments section lives (same file, optionally an extracted `<ReservationAttachmentUpload>`).
  - How to extend the pattern to other entities (Reservation/PackingItem/TripTask) — same shape: add `*_id` foreign key, reuse `TripDocumentStorage`, reuse the upload endpoint or fork it.

## Acceptance Criteria

- All programmatic checks pass.
- Manual script completes with no console errors and no regressions on the existing trip planning surfaces.
- Uploads write files to `storage/app/private/trips/{trip_id}/documents/{document_id}/` and clean them up on delete.
- Authorization is enforced on both the upload endpoint (editor required) and the file endpoint (viewer required) — never publicly accessible.
- The user can complete every workflow in the verification matrix end-to-end without leaving the trip page.
- `ai/state/document-uploads.json` is updated with `status: verified` and the full verification log.
- `ai/modules/README.md` index entry is in place.

## Out Of Scope

- Visual regression / screenshot diffing.
- Performance benchmarking on uploads larger than 15 MB or batches over 10 files (constraints reject these by design).
- Cloud storage migration validation (the disk is config-driven; switching to S3 is a separate exercise).
- Marketing copy.
- Backporting attachments to other entities (Reservation/PackingItem/TripTask) — separate module if desired.
