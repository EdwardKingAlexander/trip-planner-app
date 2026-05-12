<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TripDocumentStorage
{
    public function store(int $tripId, int $documentId, UploadedFile $file): string
    {
        $directory = "trips/{$tripId}/documents/{$documentId}";
        $filename = Str::lower(Str::random(8)).'-'.$this->sanitize($file->getClientOriginalName());

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
