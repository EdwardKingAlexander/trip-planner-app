<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripReminder extends Model
{
    protected $fillable = ['trip_id', 'remindable_type', 'remindable_id', 'label', 'remind_at', 'timezone', 'delivery_channels', 'sent_at'];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'delivery_channels' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
