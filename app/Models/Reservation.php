<?php

namespace App\Models;

use App\Concerns\TracksAuthor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use TracksAuthor;

    protected $fillable = [
        'trip_id',
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

    public function tripDays(): BelongsToMany
    {
        return $this->belongsToMany(TripDay::class, 'trip_day_reservation')->orderBy('trip_days.date');
    }

    /**
     * Return the trip-local calendar dates this reservation overlaps.
     *
     * @return array<int, CarbonImmutable>
     */
    public function computeOverlappingDates(Trip $trip): array
    {
        $startsAt = $this->dateTimeInTimezone('starts_at', $this->starts_timezone);

        if ($startsAt === null) {
            return [];
        }

        $workingTimezone = $trip->destination_timezone ?: $this->starts_timezone ?: 'UTC';
        $endsAt = $this->dateTimeInTimezone('ends_at', $this->ends_timezone) ?? $startsAt;

        $firstDate = $startsAt->setTimezone($workingTimezone)->startOfDay();
        $lastDate = $endsAt->setTimezone($workingTimezone)->startOfDay();

        if ($this->type === 'lodging' && $this->ends_at !== null && $lastDate->greaterThan($firstDate)) {
            $lastDate = $lastDate->subDay();
        }

        if ($lastDate->lessThan($firstDate)) {
            $lastDate = $firstDate;
        }

        $tripDates = $trip->days()
            ->whereBetween('date', [$firstDate->toDateString(), $lastDate->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse((string) $date, $workingTimezone)->startOfDay()->toDateString())
            ->all();

        $tripDateSet = array_flip($tripDates);
        $dates = [];

        for ($date = $firstDate; $date->lte($lastDate); $date = $date->addDay()) {
            if (isset($tripDateSet[$date->toDateString()])) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function dateTimeInTimezone(string $attribute, ?string $timezone): ?CarbonImmutable
    {
        $value = $this->getRawOriginal($attribute);

        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, $timezone ?: 'UTC');
    }
}
