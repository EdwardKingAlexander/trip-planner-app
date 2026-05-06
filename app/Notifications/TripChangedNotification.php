<?php

namespace App\Notifications;

use App\Models\TripActivityEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripChangedNotification extends Notification
{
    use Queueable;

    public function __construct(public TripActivityEvent $event) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $metadata = $this->event->metadata ?? [];

        return [
            'event_id' => $this->event->id,
            'trip_id' => $this->event->trip_id,
            'trip_name' => $metadata['trip_name'] ?? null,
            'actor_user_id' => $this->event->user_id,
            'actor_name' => $metadata['actor_name'] ?? null,
            'actor_first_name' => $metadata['actor_first_name'] ?? null,
            'event_type' => $this->event->event_type,
            'changed_area' => $this->event->changed_area,
            'subject_type' => $this->event->subject_type,
            'subject_id' => $this->event->subject_id,
            'summary' => $this->event->summary,
        ];
    }
}
