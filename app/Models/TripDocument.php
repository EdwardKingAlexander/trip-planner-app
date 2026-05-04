<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripDocument extends Model
{
    protected $fillable = ['trip_id', 'reservation_id', 'title', 'document_type', 'file_path', 'expires_on', 'notes'];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
