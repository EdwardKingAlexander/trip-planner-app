<?php

namespace App\Models;

use App\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TripDocument extends Model
{
    use TracksAuthor;

    protected $fillable = [
        'trip_id',
        'reservation_id',
        'title',
        'document_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'expires_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'file_size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (TripDocument $document): void {
            if ($document->file_path !== null) {
                Storage::disk('local')->delete($document->file_path);
            }
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function previewKind(): string
    {
        if ($this->file_path === null) {
            return 'note';
        }

        if ($this->mime_type === 'application/pdf') {
            return 'pdf';
        }

        if (in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'image';
        }

        if (in_array($this->mime_type, ['image/heic', 'image/heif'], true)) {
            return 'image-opaque';
        }

        return 'file';
    }
}
