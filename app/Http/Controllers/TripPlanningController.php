<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripPlanningController extends Controller
{
    public function cost(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->costs()->create($request->validate([
            'category' => ['required', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:160'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Cost added.');
    }

    public function packing(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->packingItems()->create($request->validate([
            'traveler_name' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:160'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Packing item added.');
    }

    public function task(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->tasks()->create($request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,normal,high'],
        ]));

        return back()->with('success', 'Task added.');
    }

    public function document(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->documents()->create($request->validate([
            'title' => ['required', 'string', 'max:160'],
            'document_type' => ['required', 'string', 'max:80'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Document note added.');
    }

    public function reminder(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->reminders()->create($request->validate([
            'label' => ['required', 'string', 'max:160'],
            'remind_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
        ]) + ['delivery_channels' => ['in_app', 'email']]);

        return back()->with('success', 'Reminder added.');
    }
}
