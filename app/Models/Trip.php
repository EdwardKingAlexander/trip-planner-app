<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'destination',
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
        $validDates = [];
        $date = $startDate ?? $this->starts_on->toDateString();
        $endDate ??= $this->ends_on->toDateString();
        $order = 0;

        while ($date <= $endDate) {
            $validDates[] = $date;

            DB::table('trip_days')->updateOrInsert(
                ['trip_id' => $this->id, 'date' => $date],
                [
                    'sort_order' => $order,
                    'title' => 'Day '.($order + 1),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $date = date('Y-m-d', strtotime($date.' +1 day'));
            $order++;
        }

        $this->days()->whereNotIn('date', $validDates)->delete();
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
        $days = $this->starts_on->diffInDays($this->ends_on) + 1;

        return $days === 1 ? '1 day' : "{$days} days";
    }

    public function timingBucket(?CarbonInterface $today = null): string
    {
        $today ??= now();

        if ($this->status === 'archived') {
            return 'archived';
        }

        if ($this->ends_on->lt($today->startOfDay())) {
            return 'past';
        }

        if ($this->starts_on->lte($today) && $this->ends_on->gte($today)) {
            return 'active';
        }

        return 'upcoming';
    }
}
