<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripAutomationSuggestion;
use App\Services\TripAutomationService;
use Illuminate\Http\RedirectResponse;

class TripAutomationController extends Controller
{
    public function refresh(Trip $trip, TripAutomationService $automation): RedirectResponse
    {
        $this->authorize('update', $trip);

        $automation->refresh($trip);

        return back()->with('success', 'Automation suggestions refreshed.');
    }

    public function accept(Trip $trip, TripAutomationSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($suggestion->trip_id === $trip->id, 404);

        if ($suggestion->suggestion_type === 'packing_template') {
            foreach (($suggestion->payload['items'] ?? []) as $index => $label) {
                $trip->packingItems()->firstOrCreate(
                    ['label' => $label],
                    ['category' => 'general', 'quantity' => 1, 'sort_order' => $index],
                );
            }
        }

        if ($suggestion->suggestion_type === 'starter_reminders') {
            foreach (($suggestion->payload['labels'] ?? []) as $index => $label) {
                $trip->reminders()->firstOrCreate(
                    ['label' => $label],
                    [
                        'remind_at' => $trip->starts_on->copy()->subDays(max(1, 7 - $index))->setTime(9, 0),
                        'timezone' => 'UTC',
                        'delivery_channels' => ['in_app', 'email'],
                    ],
                );
            }
        }

        $suggestion->update(['accepted_at' => now()]);

        return back()->with('success', 'Suggestion accepted.');
    }

    public function dismiss(Trip $trip, TripAutomationSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($suggestion->trip_id === $trip->id, 404);

        $suggestion->update(['dismissed_at' => now()]);

        return back()->with('success', 'Suggestion dismissed.');
    }
}
