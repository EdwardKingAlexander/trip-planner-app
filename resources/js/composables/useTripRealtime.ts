import { usePage, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';

type LastEvent = {
    id: number;
    changed_area: string | null;
    event_type: string | null;
    summary: string | null;
    actor_first_name: string | null;
    actor_user_id: number | null;
    created_at: string | null;
};

type TripPayload = {
    id: number;
    activity_version: number;
    last_event: LastEvent | null;
};

type Page = {
    auth?: { user?: { id: number } };
    trip?: TripPayload;
};

/**
 * Poll the open trip page for collaboration changes and reload the trip prop
 * when another participant changes anything. The local user's own changes
 * already update the trip via the existing form flow, so toasts are skipped
 * for events the current user authored.
 */
export function useTripRealtime(intervalMs = 5000): void {
    const page = usePage<Page>();

    const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
    const activityVersion = computed(() => page.props.trip?.activity_version ?? 0);

    usePoll(intervalMs, {
        only: ['trip'],
    });

    watch(activityVersion, (next, previous) => {
        if (!next || next <= (previous ?? 0)) {
            return;
        }

        const event = page.props.trip?.last_event;

        if (!event || event.actor_user_id === currentUserId.value) {
            return;
        }

        const actor = event.actor_first_name ?? 'Someone';
        const summary = event.summary ?? 'updated the trip';

        toast(`${actor} ${summary}`);
    });
}
