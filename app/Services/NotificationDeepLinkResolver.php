<?php

namespace App\Services;

use App\Models\ItineraryItem;
use App\Models\PackingItem;
use App\Models\Reservation;
use App\Models\TripCollaborator;
use App\Models\TripCost;
use App\Models\TripDocument;
use App\Models\TripReminder;
use App\Models\TripTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;

class NotificationDeepLinkResolver
{
    /**
     * @var array<class-string<Model>, array{panel: string, anchor: string}>
     */
    public const SUBJECT_MAP = [
        ItineraryItem::class => ['panel' => 'itinerary', 'anchor' => 'itinerary-item-{id}'],
        Reservation::class => ['panel' => 'reservations', 'anchor' => 'reservation-{id}'],
        TripCost::class => ['panel' => 'budget', 'anchor' => 'cost-{id}'],
        PackingItem::class => ['panel' => 'packing', 'anchor' => 'packing-item-{id}'],
        TripTask::class => ['panel' => 'tasks', 'anchor' => 'task-{id}'],
        TripDocument::class => ['panel' => 'documents', 'anchor' => 'document-{id}'],
        TripReminder::class => ['panel' => 'reminders', 'anchor' => 'reminder-{id}'],
        TripCollaborator::class => ['panel' => 'sharing', 'anchor' => 'collaborator-{id}'],
    ];

    public function __invoke(DatabaseNotification $notification): string
    {
        $data = $notification->data ?? [];
        $tripId = $data['trip_id'] ?? null;

        if ($tripId === null) {
            return route('notifications.index', absolute: false);
        }

        $subjectType = $data['subject_type'] ?? null;
        $subjectId = $data['subject_id'] ?? null;

        if (! is_string($subjectType) || $subjectId === null || ! isset(self::SUBJECT_MAP[$subjectType])) {
            return $this->tripUrl((int) $tripId);
        }

        $target = self::SUBJECT_MAP[$subjectType];

        if (! $this->subjectExists($subjectType, $subjectId)) {
            return $this->tripUrl((int) $tripId, [
                'focus' => $target['panel'],
                'missing' => '1',
            ]);
        }

        return $this->tripUrl(
            (int) $tripId,
            ['focus' => $target['panel']],
            str_replace('{id}', (string) $subjectId, $target['anchor']),
        );
    }

    /**
     * @param  class-string<Model>  $subjectType
     */
    private function subjectExists(string $subjectType, int|string $subjectId): bool
    {
        return $subjectType::query()->whereKey($subjectId)->exists();
    }

    /**
     * @param  array<string, string>  $query
     */
    private function tripUrl(int $tripId, array $query = [], ?string $fragment = null): string
    {
        $url = route('trips.show', $tripId, absolute: false);
        $query = array_filter([
            'focus' => $query['focus'] ?? null,
            'from' => 'notification',
            'missing' => $query['missing'] ?? null,
        ], fn (?string $value): bool => $value !== null);

        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if ($fragment !== null) {
            $url .= '#'.$fragment;
        }

        return $url;
    }
}
