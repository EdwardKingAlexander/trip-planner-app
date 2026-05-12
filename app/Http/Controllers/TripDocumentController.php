<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripDocument;
use App\Services\TripCollaborationEventService;
use App\Services\TripDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'nullable',
                'integer',
                Rule::exists('reservations', 'id')->where(fn ($query) => $query->where('trip_id', $trip->id)),
            ],
        ]);

        $files = collect($request->file('files', []))->values();
        $created = collect();

        DB::transaction(function () use ($trip, $files, $validated, $storage, $events, $created): void {
            foreach ($files as $index => $file) {
                $stem = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $titlePrefix = trim((string) ($validated['title_prefix'] ?? ''));
                $title = $titlePrefix !== ''
                    ? $titlePrefix.($files->count() > 1 ? ' ('.($index + 1).')' : '')
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

                $document->update([
                    'file_path' => $storage->store($trip->id, $document->id, $file),
                ]);

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

        return back()->with('success', $count === 1 ? 'Document uploaded.' : "{$count} documents uploaded.");
    }

    public function file(Request $request, Trip $trip, TripDocument $document): StreamedResponse
    {
        $this->authorize('view', $trip);
        abort_unless($document->trip_id === $trip->id, 404);
        abort_unless($document->file_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        $filename = $document->original_filename ?? 'file';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return Storage::disk('local')->response($document->file_path, $filename, [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($filename, '"\\').'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
