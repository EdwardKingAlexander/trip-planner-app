<?php

namespace App\Models;

use App\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use TracksAuthor;

    protected $fillable = [
        'trip_id',
        'itinerary_item_id',
        'type',
        'title',
        'provider_name',
        'booking_reference',
        'booking_source',
        'status',
        'starts_at',
        'starts_timezone',
        'ends_at',
        'ends_timezone',
        'location_name',
        'address',
        'contact_phone',
        'contact_email',
        'cancellation_policy',
        'notes',
        'raw_details',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'raw_details' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function itineraryItem(): BelongsTo
    {
        return $this->belongsTo(ItineraryItem::class);
    }

    public function flightSegments(): HasMany
    {
        return $this->hasMany(FlightSegment::class)->orderBy('segment_order');
    }

    public function lodgingStay(): HasOne
    {
        return $this->hasOne(LodgingStay::class);
    }

    public function flightDetails(): HasOne
    {
        return $this->hasOne(FlightDetails::class);
    }
}
