<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightSegment extends Model
{
    protected $fillable = [
        'reservation_id',
        'airline',
        'flight_number',
        'confirmation_code',
        'departure_airport',
        'arrival_airport',
        'departs_at',
        'departure_timezone',
        'arrives_at',
        'arrival_timezone',
        'seat',
        'terminal',
        'gate',
        'baggage_claim',
        'segment_order',
    ];

    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'arrives_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
