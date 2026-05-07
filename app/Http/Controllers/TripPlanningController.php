<?php

namespace App\Http\Controllers;

use App\Models\PackingItem;
use App\Models\Trip;
use App\Models\TripCost;
use App\Models\TripDocument;
use App\Models\TripReminder;
use App\Models\TripTask;
use App\Services\TripCollaborationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripPlanningController extends Controller
{
    public function cost(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);

        $cost = $trip->costs()->create($this->validatedCost($request));

        $events->record(
            trip: $trip,
            eventType: 'cost.created',
            changedArea: 'budget',
            summary: "added cost: {$cost->label}",
            subject: $cost,
        );

        return back()->with('success', 'Cost added.');
    }

    public function updateCost(Request $request, Trip $trip, TripCost $cost, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($cost->trip_id === $trip->id, 404);

        $cost->update($this->validatedCost($request));

        $events->record(
            trip: $trip,
            eventType: 'cost.updated',
            changedArea: 'budget',
            summary: "updated cost: {$cost->label}",
            subject: $cost,
        );

        return back()->with('success', 'Cost updated.');
    }

    public function destroyCost(Trip $trip, TripCost $cost, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($cost->trip_id === $trip->id, 404);

        $label = $cost->label;
        $events->record(
            trip: $trip,
            eventType: 'cost.deleted',
            changedArea: 'budget',
            summary: "deleted cost: {$label}",
            subject: $cost,
        );

        $cost->delete();

        return back()->with('success', 'Cost deleted.');
    }

    public function packing(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);

        $item = $trip->packingItems()->create($this->validatedPacking($request));

        $events->record(
            trip: $trip,
            eventType: 'packing.created',
            changedArea: 'packing',
            summary: "added packing item: {$item->label}",
            subject: $item,
        );

        return back()->with('success', 'Packing item added.');
    }

    public function updatePacking(Request $request, Trip $trip, PackingItem $packingItem, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($packingItem->trip_id === $trip->id, 404);

        $packingItem->update($this->validatedPacking($request));

        $events->record(
            trip: $trip,
            eventType: 'packing.updated',
            changedArea: 'packing',
            summary: "updated packing item: {$packingItem->label}",
            subject: $packingItem,
        );

        return back()->with('success', 'Packing item updated.');
    }

    public function togglePacked(Request $request, Trip $trip, PackingItem $packingItem, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($packingItem->trip_id === $trip->id, 404);

        $validated = $request->validate([
            'is_packed' => ['required', 'boolean'],
        ]);

        $packingItem->update(['is_packed' => $validated['is_packed']]);

        $events->record(
            trip: $trip,
            eventType: 'packing.toggled',
            changedArea: 'packing',
            summary: ($validated['is_packed'] ? 'marked packed: ' : 'marked unpacked: ').$packingItem->label,
            subject: $packingItem,
        );

        return back()->with('success', $validated['is_packed'] ? 'Marked packed.' : 'Marked unpacked.');
    }

    public function destroyPacking(Trip $trip, PackingItem $packingItem, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($packingItem->trip_id === $trip->id, 404);

        $label = $packingItem->label;
        $events->record(
            trip: $trip,
            eventType: 'packing.deleted',
            changedArea: 'packing',
            summary: "deleted packing item: {$label}",
            subject: $packingItem,
        );

        $packingItem->delete();

        return back()->with('success', 'Packing item deleted.');
    }

    public function task(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);

        $task = $trip->tasks()->create($this->validatedTask($request));

        $events->record(
            trip: $trip,
            eventType: 'task.created',
            changedArea: 'tasks',
            summary: "added task: {$task->title}",
            subject: $task,
        );

        return back()->with('success', 'Task added.');
    }

    public function updateTask(Request $request, Trip $trip, TripTask $task, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($task->trip_id === $trip->id, 404);

        $task->update($this->validatedTask($request));

        $events->record(
            trip: $trip,
            eventType: 'task.updated',
            changedArea: 'tasks',
            summary: "updated task: {$task->title}",
            subject: $task,
        );

        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(Trip $trip, TripTask $task, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($task->trip_id === $trip->id, 404);

        $title = $task->title;
        $events->record(
            trip: $trip,
            eventType: 'task.deleted',
            changedArea: 'tasks',
            summary: "deleted task: {$title}",
            subject: $task,
        );

        $task->delete();

        return back()->with('success', 'Task deleted.');
    }

    public function document(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);

        $document = $trip->documents()->create($this->validatedDocument($request));

        $events->record(
            trip: $trip,
            eventType: 'document.created',
            changedArea: 'documents',
            summary: "added document: {$document->title}",
            subject: $document,
        );

        return back()->with('success', 'Document note added.');
    }

    public function updateDocument(Request $request, Trip $trip, TripDocument $document, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($document->trip_id === $trip->id, 404);

        $document->update($this->validatedDocument($request));

        $events->record(
            trip: $trip,
            eventType: 'document.updated',
            changedArea: 'documents',
            summary: "updated document: {$document->title}",
            subject: $document,
        );

        return back()->with('success', 'Document note updated.');
    }

    public function destroyDocument(Trip $trip, TripDocument $document, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($document->trip_id === $trip->id, 404);

        $title = $document->title;
        $events->record(
            trip: $trip,
            eventType: 'document.deleted',
            changedArea: 'documents',
            summary: "deleted document: {$title}",
            subject: $document,
        );

        $document->delete();

        return back()->with('success', 'Document note deleted.');
    }

    public function reminder(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);

        $reminder = $trip->reminders()->create($this->validatedReminder($request) + ['delivery_channels' => ['in_app', 'email']]);

        $events->record(
            trip: $trip,
            eventType: 'reminder.created',
            changedArea: 'reminders',
            summary: "added reminder: {$reminder->label}",
            subject: $reminder,
        );

        return back()->with('success', 'Reminder added.');
    }

    public function updateReminder(Request $request, Trip $trip, TripReminder $reminder, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($reminder->trip_id === $trip->id, 404);

        $reminder->update($this->validatedReminder($request));

        $events->record(
            trip: $trip,
            eventType: 'reminder.updated',
            changedArea: 'reminders',
            summary: "updated reminder: {$reminder->label}",
            subject: $reminder,
        );

        return back()->with('success', 'Reminder updated.');
    }

    public function destroyReminder(Trip $trip, TripReminder $reminder, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('update', $trip);
        abort_unless($reminder->trip_id === $trip->id, 404);

        $label = $reminder->label;
        $events->record(
            trip: $trip,
            eventType: 'reminder.deleted',
            changedArea: 'reminders',
            summary: "deleted reminder: {$label}",
            subject: $reminder,
        );

        $reminder->delete();

        return back()->with('success', 'Reminder deleted.');
    }

    private function validatedCost(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:160'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validatedPacking(Request $request): array
    {
        return $request->validate([
            'traveler_name' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:160'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'is_packed' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validatedTask(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,normal,high'],
        ]);
    }

    private function validatedDocument(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'document_type' => ['required', 'string', 'max:80'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validatedReminder(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'remind_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
