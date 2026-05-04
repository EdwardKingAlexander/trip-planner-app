<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgingStay extends Model
{
    protected $fillable = [
        'reservation_id',
        'property_name',
        'room_type',
        'check_in_at',
        'check_in_timezone',
        'check_out_at',
        'check_out_timezone',
        'address',
        'phone',
        'late_arrival_note',
        'parking_details',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
