<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripCollaboratorController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('share', $trip);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:viewer,editor'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        $trip->collaborators()->updateOrCreate(
            ['email' => $validated['email']],
            [
                'user_id' => $user?->id,
                'role' => $validated['role'],
                'accepted_at' => $user ? now() : null,
            ],
        );

        return back()->with('success', 'Trip collaborator added.');
    }

    public function destroy(Trip $trip, int $collaborator): RedirectResponse
    {
        $this->authorize('share', $trip);

        $trip->collaborators()->whereKey($collaborator)->delete();

        return back()->with('success', 'Trip collaborator removed.');
    }
}
