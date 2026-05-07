<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Clock, MapPinned } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as calendarIndex } from '@/routes/calendar';
import { show as tripShow } from '@/routes/trips';

type CalendarEvent = {
    id: string;
    kind: 'itinerary' | 'reservation' | 'task' | 'reminder';
    title: string;
    trip: { id: number; name: string };
    startsAt: string | null;
    endsAt: string | null;
    allDay: boolean;
};

const props = defineProps<{
    month: string;
    timezone: string;
    events: CalendarEvent[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendarIndex(),
            },
        ],
    },
});

const monthDate = computed(() => new Date(`${props.month}T00:00:00`));
const monthLabel = computed(() =>
    new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric', timeZone: props.timezone }).format(monthDate.value),
);

const previousMonth = computed(() => {
    const date = new Date(monthDate.value);
    date.setMonth(date.getMonth() - 1);

    return calendarIndex({ query: { month: date.toISOString().slice(0, 10) } });
});

const nextMonth = computed(() => {
    const date = new Date(monthDate.value);
    date.setMonth(date.getMonth() + 1);

    return calendarIndex({ query: { month: date.toISOString().slice(0, 10) } });
});

const calendarDays = computed(() => {
    const first = new Date(monthDate.value);
    first.setDate(1 - first.getDay());

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(first);
        date.setDate(first.getDate() + index);
        const dateKey = date.toISOString().slice(0, 10);

        return {
            date,
            dateKey,
            isCurrentMonth: date.getMonth() === monthDate.value.getMonth(),
            events: props.events.filter((event) => event.startsAt?.slice(0, 10) === dateKey),
        };
    });
});

const agendaDays = computed(() => calendarDays.value.filter((day) => day.events.length > 0));

function timeLabel(value: string | null): string {
    if (!value) {
        return 'All day';
    }

    return new Intl.DateTimeFormat('en', {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: props.timezone,
    }).format(new Date(value));
}

function panelForEvent(event: CalendarEvent): string {
    if (event.kind === 'reservation') {
        return 'reservations';
    }

    if (event.kind === 'task') {
        return 'tasks';
    }

    if (event.kind === 'reminder') {
        return 'sharing';
    }

    return 'itinerary';
}
</script>

<template>
    <Head title="Calendar" />

    <div class="travel-page">
        <div class="travel-container">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <Heading title="Calendar" :description="`Cross-trip schedule in ${timezone}`" />
                <div class="flex items-center gap-2">
                    <Button as-child variant="outline" size="icon" aria-label="Previous month">
                        <Link :href="previousMonth"><ChevronLeft class="size-4" /></Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="calendarIndex()">Today</Link>
                    </Button>
                    <Button as-child variant="outline" size="icon" aria-label="Next month">
                        <Link :href="nextMonth"><ChevronRight class="size-4" /></Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ monthLabel }}</CardTitle>
                    <div class="text-sm text-muted-foreground">{{ events.length }} event{{ events.length === 1 ? '' : 's' }}</div>
                </CardHeader>
                <CardContent>
                    <div class="hidden overflow-hidden rounded-lg border border-border lg:block">
                        <div class="grid grid-cols-7 border-b border-border bg-muted text-xs font-medium uppercase text-muted-foreground">
                            <div v-for="dayName in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="dayName" class="p-3">
                                {{ dayName }}
                            </div>
                        </div>
                        <div class="grid grid-cols-7">
                            <div
                                v-for="day in calendarDays"
                                :key="day.dateKey"
                                class="min-h-32 border-r border-b border-border p-2 last:border-r-0"
                                :class="{ 'bg-muted/40 text-muted-foreground': !day.isCurrentMonth }"
                            >
                                <div class="text-sm font-medium">{{ day.date.getDate() }}</div>
                                <div class="mt-2 space-y-1">
                                    <Link
                                        v-for="event in day.events.slice(0, 3)"
                                        :key="event.id"
                                        :href="`${tripShow.url(event.trip.id)}#${panelForEvent(event)}`"
                                        class="block rounded-md bg-primary/10 px-2 py-1 text-xs text-primary hover:bg-primary/15"
                                    >
                                        {{ timeLabel(event.startsAt) }} · {{ event.title }}
                                    </Link>
                                    <div v-if="day.events.length > 3" class="text-xs text-muted-foreground">
                                        +{{ day.events.length - 3 }} more
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 lg:hidden">
                        <div v-if="agendaDays.length === 0" class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                            No scheduled trip items this month.
                        </div>
                        <section v-for="day in agendaDays" :key="day.dateKey" class="space-y-2">
                            <h2 class="text-sm font-semibold">
                                {{ new Intl.DateTimeFormat('en', { weekday: 'long', month: 'short', day: 'numeric' }).format(day.date) }}
                            </h2>
                            <Link
                                v-for="event in day.events"
                                :key="event.id"
                                :href="`${tripShow.url(event.trip.id)}#${panelForEvent(event)}`"
                                class="flex gap-3 rounded-lg border border-border bg-card p-3 text-sm"
                            >
                                <Clock class="mt-0.5 size-4 text-primary" />
                                <span>
                                    <span class="block font-medium">{{ event.title }}</span>
                                    <span class="text-muted-foreground">{{ timeLabel(event.startsAt) }} · {{ event.trip.name }}</span>
                                </span>
                            </Link>
                        </section>
                    </div>

                    <div v-if="events.length === 0" class="hidden rounded-lg border border-dashed border-border p-10 text-center lg:block">
                        <MapPinned class="mx-auto size-8 text-primary" />
                        <p class="mt-3 text-sm text-muted-foreground">No scheduled trip items this month.</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
