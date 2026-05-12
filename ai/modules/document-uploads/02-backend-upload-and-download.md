# Phase 02 - Backend Upload And Download

## Goal

Ship the two new endpoints — `POST .../documents/upload` and `GET .../documents/{document}/file` — with full test coverage. The frontend in Phases 03–04 has a stable backend to call.

## Status

- Status: `verified`
- Owner: Codex
- Depends on: Phase 01 (migration and serializer fields landed)
- Blocker: none

## Audit Inputs

- `app/Http/Controllers/TripPlanningController.php:251-302` — existing `document` / `updateDocument` / `destroyDocument`. New upload action lives here for consistency, or in a dedicated `TripDocumentController` if planning prefers separation. Decision below.
- `app/Services/TripCollaborationEventService.php:22-83` — existing event service. Reused for `document.uploaded` activity events.
- `app/Policies/TripPolicy.php` — existing trip policies (`view`, `update`). Upload uses `update`; download uses `view`.
- `routes/web.php:49-51` — existing document routes. Two new routes added below.
- `config/filesystems.php` — `local` disk writes to `storage/app/private` (private; never publicly served).

## Decision: controller location

The new actions live in a dedicated `app/Http/Controllers/TripDocumentController.php` because:

- Upload + download are different concerns from the text-note create flow (different validation rules, multipart vs JSON, file streaming vs redirect).
- It keeps `TripPlanningController` from growing past its current 7-entity scope.
- A dedicated controller leaves a clean place to add follow-up endpoints (`replace`, `bulk-delete`, etc.) without polluting the planning controller.

The existing `document` / `updateDocument` / `destroyDocument` actions **stay** on `TripPlanningController` for now. A future cleanup may move them; not in scope here.

## Planned Changes

### `app/Services/TripDocumentStorage.php` (new)

Single-purpose helper that owns path generation, sanitization, and the write itself. Keeps the controller thin and gives tests one place to assert path shape.

```php
namespace App\Services;

use App\Models\TripDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class TripDocumentStorage
{
    public function store(int $tripId, int $documentId, UploadedFile $file): string
    {
        $directory = "trips/{$tripId}/documents/{$documentId}";
        $hash = Str::lower(Str::random(8));
        $sanitized = $this->sanitize($file->getClientOriginalName());
        $filename = "{$hash}-{$sanitized}";

        Storage::disk('local')->putFileAs($directory, $file, $filename);

        return "{$directory}/{$filename}";
    }

    private function sanitize(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = pathinfo($name, PATHINFO_FILENAME);

        $stem = Str::lower($stem);
        $stem = preg_replace('/[^a-z0-9._-]+/', '-', $stem) ?? '';
        $stem = preg_replace('/-+/', '-', $stem) ?? '';
        $stem = trim($stem, '-.');

        $stem = $stem === '' ? 'file' : $stem;
        $extension = Str::lower(preg_replace('/[^a-z0-9]+/i', '', (string) $extension) ?? '');

        return $extension === '' ? $stem : "{$stem}.{$extension}";
    }
}
```

### `app/Http/Controllers/TripDocumentController.php` (new)

```php
class TripDocumentController extends Controller
{
    public function upload(
        Request $request,
        Trip $trip,
        TripCollaborationEventService $events,
        TripDocumentStorage $storage,
    ): RedirectResponse {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif'],
            'title_prefix' => ['nullable', 'string', 'max:120'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reservation_id' => [
                'nullable', 'integer',
                Rule::exists('reservations', 'id')->where(fn ($q) => $q->where('trip_id', $trip->id)),
            ],
        ]);

        $files = collect($request->file('files'))->values();
        $created = collect();

        DB::transaction(function () use ($trip, $files, $validated, $storage, $events, &$created) {
            $titlePrefix = $validated['title_prefix'] ?? null;

            foreach ($files as $index => $file) {
                $stem = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $suffix = $files->count() > 1 ? ' ('.($index + 1).')' : '';
                $title = $titlePrefix !== null
                    ? trim($titlePrefix.$suffix)
                    : $stem;

                $document = $trip->documents()->create([
                    'reservation_id' => $validated['reservation_id'] ?? null,
                    'title' => $title,
                    'document_type' => $validated['document_type'] ?? 'attachment',
                    'expires_on' => $validated['expires_on'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size_bytes' => $file->getSize(),
                ]);

                $path = $storage->store($trip->id, $document->id, $file);
                $document->update(['file_path' => $path]);

                $events->record(
                    trip: $trip,
                    eventType: 'document.uploaded',
                    changedArea: 'documents',
                    summary: "uploaded document: {$document->title}",
                    subject: $document,
                    metadata: [
                        'original_filename' => $document->original_filename,
                        'file_size_bytes' => $document->file_size_bytes,
                    ],
                );

                $created->push($document);
            }
        });

        $count = $created->count();

        return back()->with('success', $count === 1
            ? 'Document uploaded.'
            : "{$count} documents uploaded.");
    }

    public function file(Request $request, Trip $trip, TripDocument $document)
    {
        $this->authorize('view', $trip);
        abort_unless($document->trip_id === $trip->id, 404);
        abort_unless($document->file_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_filename ?? 'file',
            [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($document->original_filename ?? 'file').'"',
                'Cache-Control' => 'private, max-age=300',
            ],
        );
    }
}
```

### `routes/web.php`

```php
Route::post('trips/{trip}/documents/upload', [TripDocumentController::class, 'upload'])
    ->middleware(['auth'])
    ->name('trips.documents.upload');

Route::get('trips/{trip}/documents/{document}/file', [TripDocumentController::class, 'file'])
    ->middleware(['auth'])
    ->name('trips.documents.file');
```

Order matters: register the `upload` route before the parametric `{document}` routes so route resolution doesn't try to match `upload` as a document id.

### `app/Http/Controllers/TripPlanningController.php`

Three small adjustments:

- `validatedDocument()` gains the `reservation_id` rule from Phase 01.
- `document()` (text-note create) passes `reservation_id` through when set.
- `updateDocument()` passes `reservation_id` through.

No changes to `destroyDocument()` — the model's `deleting` hook handles the file cleanup transparently.

### `app/Models/Trip.php`

Add a `deleting` hook on Trip to handle force-deletes (since DB-level cascade bypasses model events on `TripDocument`):

```php
protected static function booted(): void
{
    static::deleting(function (Trip $trip) {
        if ($trip->isForceDeleting()) {
            $trip->documents()->each(fn ($d) => $d->delete());
        }
    });
}
```

Soft deletes leave files in place (matches the existing soft-delete semantics for the rest of the trip's data).

If `Trip` already has a `booted()`, merge into it. If not, this is a small new addition — no behavioral regression because the hook short-circuits unless the trip is being force-deleted.

### Wayfinder regeneration

After the routes land, run `php artisan wayfinder:generate --with-form --no-interaction`. The new helpers (`trips.documents.upload`, `trips.documents.file`) appear under `resources/js/routes/trips/documents.ts`. The frontend (Phase 03) imports them by name.

## Tests

`tests/Feature/TripDocumentUploadTest.php` (new, Pest):

- `it('uploads a single PDF and creates a document row')` — `Storage::fake('local')`, post multipart with one PDF, assert 302, assert `trip_documents` row, assert file exists on disk under the expected path, assert metadata fields.
- `it('uploads multiple files and creates one row per file')` — 3 files, assert 3 rows, assert all 3 files on disk, assert activity feed has 3 `document.uploaded` events.
- `it('uses title_prefix with numbered suffixes for multi-file uploads')` — prefix "Boarding Pass" + 3 files → titles "Boarding Pass (1)", "Boarding Pass (2)", "Boarding Pass (3)".
- `it('defaults title to the original filename stem when no prefix is given')` — upload "passport-scan.pdf" without prefix → title "passport-scan".
- `it('links the new rows to a reservation when reservation_id is provided')` — reservation on this trip, assert all rows have `reservation_id`.
- `it('rejects a reservation_id from a different trip')` — 422.
- `it('rejects files larger than 15 MB')` — `UploadedFile::fake()->create('big.pdf', 16000)` (16 MB) → 422 with `files.0` error.
- `it('rejects disallowed MIME types')` — `.zip`, `.exe`, `.doc` → 422.
- `it('rejects more than 10 files in one request')` — 11 files → 422.
- `it('rejects when the user is not an editor on the trip')` — viewer-role collaborator → 403, no files written.
- `it('requires auth')` — guest → 302 to login.
- `it('rolls back the row when the storage write fails')` — fake a storage failure (e.g. permission denied on the disk), assert no row remains and no orphan file. *(Implementation may use a try/catch around `$storage->store()` inside the transaction; document the failure semantics here.)*
- `it('streams a stored file for trip participants')` — upload, fetch via `trips.documents.file`, assert 200 + content-type matches + content matches.
- `it('serves Content-Disposition: inline')` — assert the header.
- `it('returns 403 for users without view access')` — non-participant → 403.
- `it('returns 404 when the document belongs to a different trip')` — same auth, different `trip_id` in URL → 404.
- `it('returns 404 when the underlying file is missing on disk')` — manually delete the file, fetch → 404.
- `it('emits a document.uploaded event distinct from document.created')` — assert `trip_activity_events` row with `event_type = document.uploaded` (and confirm `document.created` still fires for text-note creation through `TripPlanningController@document`).

`tests/Feature/TripDocumentDeleteTest.php` (extend existing tests or add new):

- `it('deletes the underlying file when a document row is deleted')` — upload, delete, assert disk no longer has the file.
- `it('keeps files when the trip is soft-deleted')` — upload, soft-delete the trip, assert file still exists.
- `it('cleans up files on trip force-delete')` — upload, `Trip::forceDelete()`, assert files removed.

`tests/Feature/TripDocumentLinkTest.php` (new, Pest):

- `it('allows updateDocument to link or unlink a reservation')` — patch with `reservation_id`, assert row updated and `document.updated` event emitted; patch again with `null`, assert unlinked.
- `it('rejects updateDocument with a reservation_id from a different trip')` — 422.

`tests/Feature/TripDocumentSerializerTest.php` (new, Pest):

- `it('includes file_url, preview_kind, and size_label on documents')` — visit trip show, assert payload shape.
- `it('includes document_count on reservations')` — two documents linked to one reservation, assert `document_count = 2`.

Existing trip planning tests (`TripManagementTest`, etc.) should be **unchanged** and pass — the new field on `validatedDocument()` is nullable, so the existing test fixtures continue to work without modification.

## State Management

- DB: `trip_documents` rows are authoritative. The new columns are populated only via the upload action; text-note rows keep them `null`.
- Disk: `storage/app/private/trips/{trip_id}/documents/{document_id}/...` is owned by the row's lifecycle.
- Activity events: one `document.uploaded` row per file in a multi-file upload (matches the per-row semantics; the activity feed reads naturally).
- No queues, no caches. File operations are synchronous on the request thread — 15 MB on a `local` disk completes in well under a second on Herd.

## Acceptance Criteria

- `TripDocumentStorage` produces stable, collision-free paths under `storage/app/private/trips/{trip_id}/documents/{document_id}/`.
- `TripDocumentController@upload` accepts multipart, validates, persists, and creates one row per file with all metadata populated.
- `TripDocumentController@file` streams the underlying file with the correct `Content-Type` and `Content-Disposition: inline`, gated by the trip view policy.
- The text-note `document` create/update endpoints accept `reservation_id`.
- The model `deleting` hook removes the file; the trip force-delete path cleans up cascaded documents.
- All assertions in the new `TripDocumentUploadTest`, `TripDocumentDeleteTest`, `TripDocumentLinkTest`, and `TripDocumentSerializerTest` pass.
- Existing tests (`TripManagementTest`, `PackingTogglePackedTest`, etc.) still pass.
- `php artisan test --compact` is green.
- `php artisan wayfinder:generate --with-form --no-interaction` regenerates cleanly.
- `vendor/bin/pint --dirty --format agent` is clean.

## Out Of Scope

- Frontend consumption (Phase 03 / 04).
- Replace-in-place / new-version flows.
- Antivirus, OCR, thumbnail generation.
- Signed temporary URLs for off-domain sharing.
- Cloud storage migration.
- Bulk delete endpoints.
