<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReleaseChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $description,
        public ?string $releaseSha = null,
        public ?string $branch = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
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
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'event_type' => 'release.changed',
            'changed_area' => 'release',
            'actor_name' => 'App release',
            'actor_first_name' => 'App',
            'summary' => $this->description,
            'release_sha' => $this->releaseSha,
            'branch' => $this->branch,
        ];
    }
}
