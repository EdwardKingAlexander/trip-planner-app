<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripAutomationSuggestion extends Model
{
    protected $fillable = [
        'trip_id',
        'suggestion_type',
        'summary',
        'payload',
        'accepted_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'accepted_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
