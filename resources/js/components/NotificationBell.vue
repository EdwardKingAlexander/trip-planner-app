<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { go as notificationGo } from '@/routes/notifications';
import type { NotificationsPayload, TripNotification } from '@/types';

const page = usePage<{ notifications?: NotificationsPayload }>();

const unreadCount = computed(() => page.props.notifications?.unread_count ?? 0);
const recent = computed<TripNotification[]>(() => page.props.notifications?.recent ?? []);
const hasUnread = computed(() => unreadCount.value > 0);

const formatRelative = (iso: string | null): string => {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

    if (seconds < 60) {
        return 'just now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m ago`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h ago`;
    }

    return `${Math.floor(seconds / 86400)}d ago`;
};

const summaryText = (notification: TripNotification): string => {
    const actor = notification.actor_first_name ?? 'Someone';
    const summary = notification.summary ?? 'made a change';

    return `${actor} ${summary}`;
};

const openNotification = (notification: TripNotification): void => {
    notification.read_at ??= new Date().toISOString();

    router.visit(notificationGo(notification.id).url);
};

const markAllRead = (): void => {
    router.post(
        '/notifications/read-all',
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="travel-touch relative size-11 rounded-lg border border-border bg-card text-primary shadow-xs hover:bg-accent dark:border-border dark:bg-card dark:text-primary dark:hover:bg-accent"
                aria-label="Notifications"
            >
                <Bell class="size-5" />
                <span
                    v-if="hasUnread"
                    class="absolute -top-1 -right-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground shadow-sm"
                >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel class="flex items-center justify-between">
                <span>Notifications</span>
                <button
                    v-if="hasUnread"
                    type="button"
                    class="text-xs font-normal text-primary hover:underline"
                    @click="markAllRead"
                >
                    Mark all read
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <div v-if="recent.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground dark:text-muted-foreground">
                You're all caught up.
            </div>
            <DropdownMenuItem
                v-for="notification in recent"
                :key="notification.id"
                :class="['cursor-pointer items-start gap-2 py-2', notification.read_at ? '' : 'bg-accent/60 dark:bg-accent/40']"
                @select="openNotification(notification)"
            >
                <div class="min-w-0 flex-1">
                    <div class="text-sm leading-snug">{{ summaryText(notification) }}</div>
                    <div class="mt-0.5 text-xs text-muted-foreground dark:text-muted-foreground">
                        <span v-if="notification.trip_name">{{ notification.trip_name }} · </span>{{ formatRelative(notification.created_at) }}
                    </div>
                </div>
                <span v-if="!notification.read_at" class="mt-1 size-2 shrink-0 rounded-full bg-primary" aria-hidden="true" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
