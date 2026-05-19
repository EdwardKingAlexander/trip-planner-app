<?php

namespace App\Models;

use App\Support\TimezoneLookup;
use App\Support\TripDates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'destination',
        'destination_timezone',
        'home_timezone',
        'starts_on',
        'ends_on',
        'status',
        'summary',
        'cover_theme',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Trip $trip): void {
            if (! $trip->isForceDeleting()) {
                return;
            }

            $trip->documents()->each(fn (TripDocument $document) => $document->delete());
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(TripCollaborator::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TripDay::class)->orderBy('date');
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(ItineraryItem::class)->orderBy('starts_at')->orderBy('sort_order');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->orderBy('starts_at');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(TripCost::class);
    }

    public function packingItems(): HasMany
    {
        return $this->hasMany(PackingItem::class)->orderBy('sort_order')->orderBy('label');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TripTask::class)->orderByRaw('completed_at is not null')->orderBy('due_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TripDocument::class)->orderBy('document_type')->orderBy('title');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(TripReminder::class)->orderBy('remind_at');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(TripImportBatch::class)->latest();
    }

    public function automationSuggestions(): HasMany
    {
        return $this->hasMany(TripAutomationSuggestion::class)->latest();
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(TripActivityEvent::class);
    }

    /**
     * Latest collaboration event id for this trip. Used as a polling cursor
     * so the frontend can tell whether a remote participant changed anything.
     */
    public function activityVersion(): int
    {
        return (int) $this->activityEvents()->max('id');
    }

    public function scopeVisibleTo($query, User $user)
    {
        $query->where('user_id', $user->id)
            ->orWhereHas('collaborators', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            });
    }

    public function syncDays(?string $startDate = null, ?string $endDate = null): void
    {
        $timezone = $this->effectiveDestinationTimezone();
        $validDates = [];
        $date = TripDates::date($startDate ?? $this->starts_on->toDateString(), $timezone);
        $end = TripDates::date($endDate ?? $this->ends_on->toDateString(), $timezone);
        $order = 0;
        $kindByDate = $this->tripDayKinds($date->toDateString(), $end->toDateString());
        while ($date->lte($end)) {
            $dateString = $date->toDateString();
            $validDates[] = $dateString;
            $kind = $kindByDate[$dateString] ?? 'vacation';

            DB::table('trip_days')->updateOrInsert(
                ['trip_id' => $this->id, 'date' => $dateString],
                [
                    'sort_order' => $order,
                    'title' => 'Day '.($order + 1),
                    'kind' => $kind,
                    'is_day_one_anchor' => $order === 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $date->addDay();
            $order++;
        }

        $this->days()->whereNotIn('date', $validDates)->delete();
    }

    public function effectiveDestinationTimezone(): string
    {
        return $this->destination_timezone ?: 'UTC';
    }

    public function effectiveHomeTimezone(): string
    {
        return $this->home_timezone
            ?: $this->user?->travelPreference?->home_timezone
            ?: config('app.timezone');
    }

    public function dayOneAnchor(): ?TripDay
    {
        $days = $this->relationLoaded('days') ? $this->days : $this->days()->get();

        return $days->first();
    }

    public function vacationDayNumberFor(TripDay $day): ?int
    {
        $anchor = $this->dayOneAnchor();

        if ($anchor === null) {
            return null;
        }

        $anchorDate = Carbon::parse($anchor->date->toDateString(), $this->effectiveDestinationTimezone());
        $thisDate = Carbon::parse($day->date->toDateString(), $this->effectiveDestinationTimezone());
        $delta = (int) $anchorDate->diffInDays($thisDate, false);

        if ($delta < 0) {
            return null;
        }

        return $delta + 1;
    }

    public function dayLabelFor(TripDay $day): string
    {
        $dayNumber = $this->vacationDayNumberFor($day);

        return match (true) {
            $day->kind === 'travel-out' && $dayNumber !== null => "Travel · departure · Day {$dayNumber}",
            $day->kind === 'travel-home' && $dayNumber !== null => "Travel · home · Day {$dayNumber}",
            $day->kind === 'travel-out' => 'Travel · departure',
            $day->kind === 'travel-home' => 'Travel · home',
            $day->kind === 'arrival' && $dayNumber !== null => "Arrival · Day {$dayNumber}",
            $day->kind === 'departure' && $dayNumber !== null => "Departure · Day {$dayNumber}",
            $dayNumber !== null => "Day {$dayNumber}",
            default => 'Travel',
        };
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->collaborators()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('email', $user->email);
            })
            ->whereIn('role', ['owner', 'editor'])
            ->exists();
    }

    public function tripLengthLabel(): string
    {
        $days = TripDates::date($this->starts_on->toDateString(), $this->effectiveDestinationTimezone())
            ->diffInDays(TripDates::date($this->ends_on->toDateString(), $this->effectiveDestinationTimezone())) + 1;

        return $days === 1 ? '1 day' : "{$days} days";
    }

    public function timingBucket(?CarbonInterface $today = null): string
    {
        $today = $today
            ? Carbon::parse($today->toDateString(), $this->effectiveDestinationTimezone())->startOfDay()
            : now($this->effectiveDestinationTimezone())->startOfDay();

        if ($this->status === 'archived') {
            return 'archived';
        }

        $startsOn = TripDates::date($this->starts_on->toDateString(), $this->effectiveDestinationTimezone());
        $endsOn = TripDates::date($this->ends_on->toDateString(), $this->effectiveDestinationTimezone());

        if ($endsOn->lt($today)) {
            return 'past';
        }

        if ($startsOn->lte($today) && $endsOn->gte($today)) {
            return 'active';
        }

        return 'upcoming';
    }

    /**
     * @return array<string, string>
     */
    private function tripDayKinds(string $startDate, string $endDate): array
    {
        $kinds = [];

        $this->reservations()
            ->with('flightSegments')
            ->where('type', 'flight')
            ->get()
            ->flatMap->flightSegments
            ->each(function (FlightSegment $segment) use (&$kinds, $startDate, $endDate): void {
                if ($segment->departs_at === null || $segment->arrives_at === null) {
                    return;
                }

                $destinationTimezone = $this->effectiveDestinationTimezone();
                $arrivalTimezone = TimezoneLookup::airportTimezone($segment->arrival_airport) ?? $segment->arrival_timezone;

                if ($arrivalTimezone !== $destinationTimezone) {
                    return;
                }

                $departure = $segment->departs_at->copy()->setTimezone($segment->departure_timezone);
                $arrival = $segment->arrives_at->copy()->setTimezone($destinationTimezone);

                if ($arrival->toDateString() <= $departure->toDateString()) {
                    return;
                }

                $departureDate = $departure->toDateString();
                $arrivalDate = $arrival->toDateString();

                if ($departureDate >= $startDate && $departureDate <= $endDate) {
                    $kinds[$departureDate] = 'travel-out';
                }

                if ($arrivalDate >= $startDate && $arrivalDate <= $endDate) {
                    $kinds[$arrivalDate] = 'arrival';
                }
            });

        return $kinds;
    }
}
