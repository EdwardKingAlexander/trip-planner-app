<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripActivityEvent;
use App\Models\User;
use App\Notifications\TripChangedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class TripCollaborationEventService
{
    /**
     * Record a collaborator-visible change to a trip and fan out notifications
     * to every other participant.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Trip $trip,
        string $eventType,
        string $changedArea,
        string $summary,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
        array $excludeUserIds = [],
    ): TripActivityEvent {
        $actor ??= Auth::user();

        $event = TripActivityEvent::create([
            'trip_id' => $trip->id,
            'user_id' => $actor?->id,
            'event_type' => $eventType,
            'changed_area' => $changedArea,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'summary' => $summary,
            'metadata' => array_merge([
                'actor_name' => $actor?->name,
                'actor_first_name' => $actor?->first_name,
                'trip_name' => $trip->name,
            ], $metadata),
        ]);

        $this->notifyParticipants($trip, $event, $actor, $excludeUserIds);

        return $event;
    }

    /**
     * @param  array<int, int>  $excludeUserIds
     */
    public function notifyAdder(
        Trip $trip,
        User $adder,
        string $eventType,
        string $changedArea,
        string $summary,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
    ): TripActivityEvent {
        $actor ??= Auth::user();

        $event = TripActivityEvent::create([
            'trip_id' => $trip->id,
            'user_id' => $actor?->id,
            'event_type' => $eventType,
            'changed_area' => $changedArea,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'summary' => $summary,
            'metadata' => array_merge([
                'actor_name' => $actor?->name,
                'actor_first_name' => $actor?->first_name,
                'trip_name' => $trip->name,
                'targeted_recipient_id' => $adder->id,
            ], $metadata),
        ]);

        Notification::send($adder, new TripChangedNotification($event));

        return $event;
    }

    /**
     * @param  array<int, int>  $excludeUserIds
     */
    private function notifyParticipants(Trip $trip, TripActivityEvent $event, ?User $actor, array $excludeUserIds = []): void
    {
        $recipients = $this->participantsExceptActor($trip, $actor)
            ->reject(fn (User $user): bool => in_array($user->id, $excludeUserIds, true))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TripChangedNotification($event));
    }

    /**
     * @return Collection<int, User>
     */
    private function participantsExceptActor(Trip $trip, ?User $actor): Collection
    {
        $owner = $trip->user;
        $collaborators = $trip->collaborators()
            ->whereNotNull('user_id')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        return collect([$owner])
            ->merge($collaborators)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $actor !== null && $user->id === $actor->id)
            ->values();
    }
}
