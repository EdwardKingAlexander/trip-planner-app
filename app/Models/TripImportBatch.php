<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripImportBatch extends Model
{
    protected $fillable = [
        'trip_id',
        'user_id',
        'source_type',
        'status',
        'original_file_path',
        'raw_text',
        'parsed_payload',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed_payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
