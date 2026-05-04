<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingItem extends Model
{
    protected $fillable = ['trip_id', 'traveler_name', 'category', 'label', 'quantity', 'is_packed', 'sort_order', 'notes'];

    protected function casts(): array
    {
        return ['is_packed' => 'boolean'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
