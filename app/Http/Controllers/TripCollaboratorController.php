<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripCollaborationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripCollaboratorController extends Controller
{
    public function store(Request $request, Trip $trip, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('share', $trip);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:viewer,editor'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        $collaborator = $trip->collaborators()->updateOrCreate(
            ['email' => $validated['email']],
            [
                'user_id' => $user?->id,
                'role' => $validated['role'],
                'accepted_at' => $user ? now() : null,
            ],
        );

        $events->record(
            trip: $trip,
            eventType: 'collaborator.added',
            changedArea: 'sharing',
            summary: "shared the trip with {$validated['email']} as {$validated['role']}",
            subject: $collaborator,
        );

        return back()->with('success', 'Trip collaborator added.');
    }

    public function destroy(Trip $trip, int $collaborator, TripCollaborationEventService $events): RedirectResponse
    {
        $this->authorize('share', $trip);

        $row = $trip->collaborators()->whereKey($collaborator)->first();
        $trip->collaborators()->whereKey($collaborator)->delete();

        if ($row !== null) {
            $events->record(
                trip: $trip,
                eventType: 'collaborator.removed',
                changedArea: 'sharing',
                summary: "removed {$row->email} from the trip",
            );
        }

        return back()->with('success', 'Trip collaborator removed.');
    }
}
