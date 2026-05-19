<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $timezone = $request->user()->travelPreference?->home_timezone ?? config('app.timezone');
        $month = Carbon::parse($request->query('month', now($timezone)->toDateString()), $timezone)->startOfMonth();
        $windowStart = $month->copy()->startOfWeek();
        $windowEnd = $month->copy()->endOfMonth()->endOfWeek();

        $trips = Trip::query()
            ->visibleTo($request->user())
            ->with([
                'itineraryItems' => fn ($query) => $query
                    ->whereBetween('starts_at', [$windowStart, $windowEnd])
                    ->orderBy('starts_at'),
                'reservations' => fn ($query) => $query
                    ->whereBetween('starts_at', [$windowStart, $windowEnd])
                    ->orderBy('starts_at'),
                'tasks' => fn ($query) => $query
                    ->whereBetween('due_at', [$windowStart, $windowEnd])
                    ->orderBy('due_at'),
                'reminders' => fn ($query) => $query
                    ->whereBetween('remind_at', [$windowStart, $windowEnd])
                    ->orderBy('remind_at'),
            ])
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->whereBetween('starts_on', [$windowStart->toDateString(), $windowEnd->toDateString()])
                    ->orWhereBetween('ends_on', [$windowStart->toDateString(), $windowEnd->toDateString()])
                    ->orWhere(function ($query) use ($windowStart, $windowEnd) {
                        $query->where('starts_on', '<=', $windowStart->toDateString())
                            ->where('ends_on', '>=', $windowEnd->toDateString());
                    });
            })
            ->orderBy('starts_on')
            ->get();

        return Inertia::render('calendar/Index', [
            'month' => $month->toDateString(),
            'timezone' => $timezone,
            'events' => $trips->flatMap(fn (Trip $trip) => [
                [
                    'id' => "trip-{$trip->id}",
                    'kind' => 'trip',
                    'title' => $trip->name,
                    'trip' => ['id' => $trip->id, 'name' => $trip->name],
                    'startsAt' => $trip->starts_on->toDateString(),
                    'endsAt' => $trip->ends_on->toDateString(),
                    'allDay' => true,
                    'timezone' => $trip->effectiveDestinationTimezone(),
                ],
                ...$trip->itineraryItems->map(fn ($item) => [
                    'id' => "itinerary-{$item->id}",
                    'kind' => 'itinerary',
                    'title' => $item->title,
                    'trip' => ['id' => $trip->id, 'name' => $trip->name],
                    'startsAt' => $item->starts_at?->toIso8601String(),
                    'endsAt' => $item->ends_at?->toIso8601String(),
                    'allDay' => $item->is_all_day,
                ])->all(),
                ...$trip->reservations->map(fn ($reservation) => [
                    'id' => "reservation-{$reservation->id}",
                    'kind' => 'reservation',
                    'title' => $reservation->title,
                    'trip' => ['id' => $trip->id, 'name' => $trip->name],
                    'startsAt' => $reservation->starts_at?->toIso8601String(),
                    'endsAt' => $reservation->ends_at?->toIso8601String(),
                    'allDay' => false,
                ])->all(),
                ...$trip->tasks->map(fn ($task) => [
                    'id' => "task-{$task->id}",
                    'kind' => 'task',
                    'title' => $task->title,
                    'trip' => ['id' => $trip->id, 'name' => $trip->name],
                    'startsAt' => $task->due_at?->toIso8601String(),
                    'endsAt' => null,
                    'allDay' => false,
                ])->all(),
                ...$trip->reminders->map(fn ($reminder) => [
                    'id' => "reminder-{$reminder->id}",
                    'kind' => 'reminder',
                    'title' => $reminder->label,
                    'trip' => ['id' => $trip->id, 'name' => $trip->name],
                    'startsAt' => $reminder->remind_at?->toIso8601String(),
                    'endsAt' => null,
                    'allDay' => false,
                ])->all(),
            ])->values(),
        ]);
    }
}
