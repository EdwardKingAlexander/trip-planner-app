<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        $items = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->serialize($notification));

        $payload = [
            'items' => $items,
            'unread_count' => $user->unreadNotifications()->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('notifications/Index', $payload);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DatabaseNotification $notification): array
    {
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
            'summary' => $data['summary'] ?? null,
        ];
    }
}
