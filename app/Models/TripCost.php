<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripCost extends Model
{
    protected $fillable = ['trip_id', 'reservation_id', 'category', 'label', 'planned_amount', 'actual_amount', 'currency', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
