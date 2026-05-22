<?php

namespace App\Models;

use App\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItemNote extends Model
{
    use TracksAuthor;

    protected $fillable = [
        'itinerary_item_id',
        'body',
    ];

    public function itineraryItem(): BelongsTo
    {
        return $this->belongsTo(ItineraryItem::class);
    }
}
