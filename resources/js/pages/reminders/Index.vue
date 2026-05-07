<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, Clock3 } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { done, index as remindersIndex } from '@/routes/reminders';
import { show as tripShow } from '@/routes/trips';

type Reminder = {
    id: number;
    label: string;
    notes: string | null;
    remind_at: string | null;
    timezone: string;
    sent_at: string | null;
    bucket: 'past_due' | 'today' | 'upcoming' | 'done';
    trip: { id: number; name: string };
};

const props = defineProps<{
    timezone: string;
    buckets: Record<'past_due' | 'today' | 'upcoming' | 'done', Reminder[]>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Reminders',
                href: remindersIndex(),
            },
        ],
    },
});

const sections = [
    { key: 'past_due', title: 'Past due' },
    { key: 'today', title: 'Today' },
    { key: 'upcoming', title: 'Upcoming' },
    { key: 'done', title: 'Done' },
] as const;

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'No date set';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: props.timezone,
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Reminders" />

    <div class="travel-page">
        <div class="travel-container">
            <Heading title="Reminders" :description="`Cross-trip reminder inbox in ${timezone}`" />

            <div class="grid gap-4 lg:grid-cols-2">
                <Card v-for="section in sections" :key="section.key">
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between text-base">
                            {{ section.title }}
                            <span class="text-sm font-normal text-muted-foreground">{{ buckets[section.key].length }}</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-if="buckets[section.key].length === 0" class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            Nothing here.
                        </div>

                        <article
                            v-for="reminder in buckets[section.key]"
                            :key="reminder.id"
                            class="rounded-lg border border-border bg-card p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <Link :href="`${tripShow.url(reminder.trip.id)}#reminders`" class="min-w-0">
                                    <span class="block font-medium">{{ reminder.label }}</span>
                                    <span class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                        <Clock3 class="size-4" />
                                        {{ formatDateTime(reminder.remind_at) }} · {{ reminder.trip.name }}
                                    </span>
                                </Link>
                                <Form v-if="section.key !== 'done'" v-bind="done.form(reminder.id)">
                                    <Button size="sm" variant="outline" class="travel-touch">
                                        <Check class="size-4" />
                                        Done
                                    </Button>
                                </Form>
                            </div>
                            <p v-if="reminder.notes" class="mt-3 rounded-md bg-muted p-2 text-sm text-muted-foreground">
                                {{ reminder.notes }}
                            </p>
                        </article>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
