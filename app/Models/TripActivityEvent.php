<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripActivityEvent extends Model
{
    protected $fillable = ['trip_id', 'user_id', 'event_type', 'summary', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
