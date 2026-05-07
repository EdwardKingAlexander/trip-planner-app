<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Bell, CheckCheck } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as notificationsIndex, readAll } from '@/routes/notifications';
import type { TripNotification } from '@/types';

defineProps<{
    items: TripNotification[];
    unread_count: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Notifications',
                href: notificationsIndex(),
            },
        ],
    },
});

function formatRelative(value: string | null): string {
    if (!value) {
        return '';
    }

    const seconds = Math.floor((Date.now() - new Date(value).getTime()) / 1000);

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
}
</script>

<template>
    <Head title="Notifications" />

    <div class="travel-page">
        <div class="travel-container">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <Heading title="Notifications" :description="`${unread_count} unread update${unread_count === 1 ? '' : 's'}`" />
                <Form v-if="unread_count > 0" v-bind="readAll.form()">
                    <Button variant="outline" class="travel-touch">
                        <CheckCheck class="size-4" />
                        Mark all read
                    </Button>
                </Form>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent trip activity</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-if="items.length === 0" class="rounded-lg border border-dashed border-border p-10 text-center">
                        <Bell class="mx-auto size-8 text-primary" />
                        <p class="mt-3 text-sm text-muted-foreground">No notifications yet.</p>
                    </div>

                    <Link
                        v-for="notification in items"
                        :key="notification.id"
                        :href="notification.deep_link"
                        class="flex items-start gap-3 rounded-lg border border-border bg-card p-4"
                        :class="{ 'border-primary/40 bg-primary/5': !notification.read_at }"
                    >
                        <span class="mt-1 size-2 rounded-full" :class="notification.read_at ? 'bg-muted' : 'bg-primary'" aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span class="block font-medium">
                                {{ notification.actor_first_name || 'Someone' }} {{ notification.summary || 'made a change' }}
                            </span>
                            <span class="mt-1 block text-sm text-muted-foreground">
                                {{ notification.trip_name || 'Trip update' }} · {{ formatRelative(notification.created_at) }}
                            </span>
                        </span>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
