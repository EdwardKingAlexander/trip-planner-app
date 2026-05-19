<?php

namespace App\Http\Middleware;

use App\Enums\Theme;
use App\Support\TimezoneLookup;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'theme' => $this->theme($request),
            'timezones' => fn () => Cache::rememberForever('inertia.timezones', fn (): array => TimezoneLookup::identifiers()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications' => fn () => $this->notificationsPayload($request),
        ];
    }

    private function theme(Request $request): string
    {
        $userTheme = $request->user()?->theme;

        if (Theme::tryFrom((string) $userTheme) !== null) {
            return $userTheme;
        }

        $cookieTheme = $request->cookie('theme');

        if (Theme::tryFrom((string) $cookieTheme) !== null) {
            return $cookieTheme;
        }

        return Theme::default()->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationsPayload(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['unread_count' => 0, 'recent' => []];
        }

        $recent = $user->notifications()
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (DatabaseNotification $notification) {
                $data = $notification->data ?? [];

                return [
                    'id' => $notification->id,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'trip_id' => $data['trip_id'] ?? null,
                    'trip_name' => $data['trip_name'] ?? null,
                    'actor_first_name' => $data['actor_first_name'] ?? $data['actor_name'] ?? null,
                    'changed_area' => $data['changed_area'] ?? null,
                    'event_type' => $data['event_type'] ?? null,
                    'subject_type' => $data['subject_type'] ?? null,
                    'subject_id' => $data['subject_id'] ?? null,
                    'deep_link' => route('notifications.go', $notification->id, absolute: false),
                    'summary' => $data['summary'] ?? null,
                ];
            })
            ->all();

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $recent,
        ];
    }
}
