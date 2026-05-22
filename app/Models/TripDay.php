<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripDay extends Model
{
    protected $fillable = ['trip_id', 'date', 'title', 'kind', 'is_day_one_anchor', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_day_one_anchor' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function kind(): Attribute
    {
        return Attribute::get(fn (?string $value): string => $value ?: 'vacation');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(ItineraryItem::class)->orderBy('sort_order')->orderBy('starts_at');
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'trip_day_reservation')
            ->orderBy('reservations.starts_at')
            ->orderBy('reservations.id');
    }
}
