<?php

namespace App\Http\Controllers;

use App\Models\TripReminder;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderInboxController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $timezone = $request->user()->travelPreference?->home_timezone ?? config('app.timezone');
        $now = now($timezone);
        $endOfToday = $now->copy()->endOfDay();

        $reminders = TripReminder::query()
            ->with('trip:id,name,user_id')
            ->whereHas('trip', fn ($query) => $query->visibleTo($request->user()))
            ->orderByRaw('sent_at is not null')
            ->orderBy('remind_at')
            ->get()
            ->map(fn (TripReminder $reminder) => [
                'id' => $reminder->id,
                'label' => $reminder->label,
                'notes' => $reminder->notes,
                'remind_at' => $reminder->remind_at?->toIso8601String(),
                'timezone' => $reminder->timezone,
                'sent_at' => $reminder->sent_at?->toIso8601String(),
                'bucket' => $this->bucket($reminder, $now, $endOfToday),
                'trip' => [
                    'id' => $reminder->trip->id,
                    'name' => $reminder->trip->name,
                ],
            ]);

        return Inertia::render('reminders/Index', [
            'timezone' => $timezone,
            'buckets' => [
                'past_due' => $reminders->where('bucket', 'past_due')->values(),
                'today' => $reminders->where('bucket', 'today')->values(),
                'upcoming' => $reminders->where('bucket', 'upcoming')->values(),
                'done' => $reminders->where('bucket', 'done')->values(),
            ],
        ]);
    }

    public function done(Request $request, TripReminder $reminder): RedirectResponse
    {
        abort_unless($reminder->trip()->where(fn ($query) => $query->visibleTo($request->user()))->exists(), 404);

        $reminder->forceFill(['sent_at' => now()])->save();

        return back()->with('success', 'Reminder marked done.');
    }

    private function bucket(TripReminder $reminder, CarbonInterface $now, CarbonInterface $endOfToday): string
    {
        if ($reminder->sent_at !== null) {
            return 'done';
        }

        if ($reminder->remind_at?->lt($now)) {
            return 'past_due';
        }

        if ($reminder->remind_at?->lte($endOfToday)) {
            return 'today';
        }

        return 'upcoming';
    }
}
