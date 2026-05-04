<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripDay extends Model
{
    protected $fillable = ['trip_id', 'date', 'title', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(ItineraryItem::class)->orderBy('sort_order')->orderBy('starts_at');
    }
}
