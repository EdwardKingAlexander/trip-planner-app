<?php

namespace App\Models;

use App\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ItineraryItem extends Model
{
    use TracksAuthor;

    protected $fillable = [
        'trip_id',
        'trip_day_id',
        'type',
        'title',
        'description',
        'location_name',
        'address',
        'map_url',
        'starts_at',
        'ends_at',
        'is_all_day',
        'timezone',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function tripDay(): BelongsTo
    {
        return $this->belongsTo(TripDay::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }
}
