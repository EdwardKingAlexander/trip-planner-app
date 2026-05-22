<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Luggage, MapPin, Plane, Plus, Search, Share2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TimezonePicker from '@/components/TimezonePicker.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatTripDate } from '@/lib/dates';

type TripSummary = {
    id: number;
    name: string;
    destination: string;
    effective_destination_timezone: string;
    starts_on: string;
    ends_on: string;
    status: string;
    summary: string | null;
    cover_theme: string;
    length: string;
    bucket: string;
    is_owner: boolean;
    counts: {
        days: number;
        items: number;
        reservations: number;
        tasks: number;
        documents: number;
        collaborators: number;
    };
};

const props = defineProps<{
    trips: TripSummary[];
    filters: { search: string; status?: string | null };
    stats: { total: number; upcoming: number; active: number; shared: number };
    timezones: string[];
    homeTimezone: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Trips', href: '/trips' }],
    },
});

const form = useForm({
    name: '',
    destination: '',
    destination_timezone: null as string | null,
    home_timezone: props.homeTimezone,
    starts_on: '',
    ends_on: '',
    status: 'planned',
    summary: '',
    cover_theme: 'coastal',
});

const searchForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const createTrip = () => {
    form.post('/trips', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const search = () => {
    router.get('/trips', {
        search: searchForm.search || undefined,
        status: searchForm.status || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const bucketLabel = (bucket: string) => ({
    active: 'Traveling now',
    upcoming: 'Upcoming',
    past: 'Past',
    archived: 'Archived',
}[bucket] ?? bucket);
</script>

<template>
    <Head title="Trips" />

    <div class="travel-page">
        <div class="travel-container">
            <section class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <div class="travel-hero">
                    <div class="relative min-h-64 p-6 sm:p-8">
                        <div class="travel-stripe absolute inset-x-0 top-0" />
                        <div class="grid gap-6 md:grid-cols-[1fr_auto] md:items-end">
                            <div class="space-y-4">
                                <div class="inline-flex items-center gap-2 rounded-full border border-border bg-card/80 px-3 py-1 text-xs font-medium uppercase text-primary shadow-xs dark:border-border dark:bg-card/60 dark:text-primary">
                                    <Luggage class="h-3.5 w-3.5" />
                                    Vacation command center
                                </div>
                                <div class="max-w-2xl space-y-3">
                                    <h1 class="text-3xl font-semibold leading-tight sm:text-5xl">
                                        Plan the trip, then travel from one clear screen.
                                    </h1>
                                    <p class="travel-muted max-w-xl text-sm leading-6">
                                        Track dates, reservations, documents, costs, packing, reminders, and shared details for the trips you and your girlfriend take together.
                                    </p>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2 rounded-lg border border-border bg-card/85 p-3 text-center shadow-xs dark:border-border dark:bg-card/60">
                                <div class="rounded-md bg-muted p-2 dark:bg-muted">
                                    <div class="text-2xl font-semibold">{{ stats.total }}</div>
                                    <div class="text-xs text-muted-foreground dark:text-muted-foreground">Trips</div>
                                </div>
                                <div class="rounded-md bg-muted p-2 dark:bg-muted">
                                    <div class="text-2xl font-semibold">{{ stats.upcoming }}</div>
                                    <div class="text-xs text-muted-foreground dark:text-muted-foreground">Upcoming</div>
                                </div>
                                <div class="rounded-md bg-muted p-2 dark:bg-muted">
                                    <div class="text-2xl font-semibold">{{ stats.shared }}</div>
                                    <div class="text-xs text-muted-foreground dark:text-muted-foreground">Shared</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <Card class="travel-panel">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Plus class="h-5 w-5" />
                            New Trip
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="createTrip">
                            <Input v-model="form.name" class="travel-touch" placeholder="Trip name" />
                            <InputError :message="form.errors.name" />
                            <Input v-model="form.destination" class="travel-touch" placeholder="Destination" />
                            <InputError :message="form.errors.destination" />
                            <div class="grid gap-3 min-[430px]:grid-cols-2">
                                <TimezonePicker v-model="form.destination_timezone" :timezones="timezones" placeholder="Destination timezone" />
                                <TimezonePicker v-model="form.home_timezone" :timezones="timezones" placeholder="Home timezone" />
                            </div>
                            <InputError :message="form.errors.destination_timezone || form.errors.home_timezone" />
                            <div class="grid gap-3 min-[430px]:grid-cols-2">
                                <Input v-model="form.starts_on" class="travel-touch" type="date" />
                                <Input v-model="form.ends_on" class="travel-touch" type="date" />
                            </div>
                            <InputError :message="form.errors.starts_on || form.errors.ends_on" />
                            <textarea v-model="form.summary" class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes, purpose, or must-do ideas" />
                            <Button type="submit" class="travel-button-primary w-full" :disabled="form.processing">
                                Create trip
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section class="travel-panel p-4">
                <form class="flex flex-col gap-3 md:flex-row" @submit.prevent="search">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchForm.search" class="travel-touch pl-9" placeholder="Search destinations and trip names" />
                    </div>
                    <select v-model="searchForm.status" class="travel-touch w-full rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="planned">Planned</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                    <Button type="submit" class="travel-touch" variant="outline">Filter</Button>
                </form>
            </section>

            <section v-if="trips.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="trip in trips"
                    :key="trip.id"
                    :href="`/trips/${trip.id}`"
                    class="group travel-panel overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="travel-stripe" />
                    <div class="space-y-5 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-xs font-medium uppercase text-primary dark:text-primary">{{ bucketLabel(trip.bucket) }}</div>
                                <h2 class="mt-1 text-xl font-semibold leading-tight group-hover:text-primary">{{ trip.name }}</h2>
                            </div>
                            <Share2 v-if="!trip.is_owner || trip.counts.collaborators" class="h-5 w-5 text-primary" />
                        </div>
                        <div class="travel-muted space-y-2 text-sm">
                            <div class="flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                {{ trip.destination }}
                            </div>
                            <div class="flex items-center gap-2">
                                <CalendarDays class="h-4 w-4" />
                                {{ formatTripDate(trip.starts_on, trip.effective_destination_timezone) }} - {{ formatTripDate(trip.ends_on, trip.effective_destination_timezone) }} · {{ trip.length }}
                            </div>
                            <div class="flex items-center gap-2">
                                <Plane class="h-4 w-4" />
                                {{ trip.counts.reservations }} reservations · {{ trip.counts.items }} itinerary items
                            </div>
                        </div>
                        <p v-if="trip.summary" class="travel-muted line-clamp-2 text-sm">{{ trip.summary }}</p>
                    </div>
                </Link>
            </section>

            <section v-else class="rounded-lg border border-dashed border-border bg-card p-10 text-center shadow-sm dark:border-border dark:bg-card">
                <Luggage class="mx-auto h-10 w-10 text-primary" />
                <h2 class="mt-4 text-xl font-semibold">No trips yet</h2>
                <p class="travel-muted mt-2 text-sm">Create your first trip to start organizing dates, stays, flights, and shared details.</p>
            </section>
        </div>
    </div>
</template>
