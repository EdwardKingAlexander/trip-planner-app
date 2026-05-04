<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id
            || $trip->collaborators()
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)->orWhere('email', $user->email);
                })
                ->exists();
    }

    public function update(User $user, Trip $trip): bool
    {
        return $trip->canBeEditedBy($user);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id;
    }

    public function share(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id;
    }
}
