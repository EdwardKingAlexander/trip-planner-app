<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Luggage, MapPin, Plane, Plus, Search, Share2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type TripSummary = {
    id: number;
    name: string;
    destination: string;
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
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Trips', href: '/trips' }],
    },
});

const form = useForm({
    name: '',
    destination: '',
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

const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
}).format(new Date(`${value}T00:00:00`));

const bucketLabel = (bucket: string) => ({
    active: 'Traveling now',
    upcoming: 'Upcoming',
    past: 'Past',
    archived: 'Archived',
}[bucket] ?? bucket);
</script>

<template>
    <Head title="Trips" />

    <div class="min-h-full bg-[#f7f3ec] text-[#211f1a] dark:bg-[#11100e] dark:text-[#f3efe7]">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <section class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <div class="overflow-hidden rounded-lg border border-[#d8cdbb] bg-[#fffaf1] shadow-sm dark:border-[#3b352d] dark:bg-[#181613]">
                    <div class="relative min-h-64 p-6 sm:p-8">
                        <div class="absolute inset-x-0 top-0 h-2 bg-[linear-gradient(90deg,#1b6b6f,#d98935,#b7472a,#223843)]" />
                        <div class="grid gap-6 md:grid-cols-[1fr_auto] md:items-end">
                            <div class="space-y-4">
                                <div class="inline-flex items-center gap-2 rounded-full border border-[#d8cdbb] px-3 py-1 text-xs font-medium uppercase tracking-wide text-[#6d6253] dark:border-[#4a4137] dark:text-[#bdb3a6]">
                                    <Luggage class="h-3.5 w-3.5" />
                                    Vacation command center
                                </div>
                                <div class="max-w-2xl space-y-3">
                                    <h1 class="text-3xl font-semibold leading-tight sm:text-5xl">
                                        Plan the trip, then travel from one clear screen.
                                    </h1>
                                    <p class="max-w-xl text-sm leading-6 text-[#6d6253] dark:text-[#c8beb0]">
                                        Track dates, reservations, documents, costs, packing, reminders, and shared details for the trips you and your girlfriend take together.
                                    </p>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2 rounded-lg border border-[#d8cdbb] bg-white/70 p-3 text-center dark:border-[#3b352d] dark:bg-black/20">
                                <div>
                                    <div class="text-2xl font-semibold">{{ stats.total }}</div>
                                    <div class="text-xs text-[#776d60]">Trips</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-semibold">{{ stats.upcoming }}</div>
                                    <div class="text-xs text-[#776d60]">Upcoming</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-semibold">{{ stats.shared }}</div>
                                    <div class="text-xs text-[#776d60]">Shared</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <Card class="rounded-lg border-[#d8cdbb] bg-white dark:border-[#3b352d] dark:bg-[#181613]">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Plus class="h-5 w-5" />
                            New Trip
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="createTrip">
                            <Input v-model="form.name" placeholder="Trip name" />
                            <InputError :message="form.errors.name" />
                            <Input v-model="form.destination" placeholder="Destination" />
                            <InputError :message="form.errors.destination" />
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="form.starts_on" type="date" />
                                <Input v-model="form.ends_on" type="date" />
                            </div>
                            <InputError :message="form.errors.starts_on || form.errors.ends_on" />
                            <textarea v-model="form.summary" class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes, purpose, or must-do ideas" />
                            <Button class="w-full bg-[#1b6b6f] hover:bg-[#155356]" :disabled="form.processing">
                                Create trip
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section class="rounded-lg border border-[#d8cdbb] bg-white p-4 dark:border-[#3b352d] dark:bg-[#181613]">
                <form class="flex flex-col gap-3 md:flex-row" @submit.prevent="search">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#776d60]" />
                        <Input v-model="searchForm.search" class="pl-9" placeholder="Search destinations and trip names" />
                    </div>
                    <select v-model="searchForm.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="planned">Planned</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                    <Button type="submit" variant="outline">Filter</Button>
                </form>
            </section>

            <section v-if="trips.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="trip in trips"
                    :key="trip.id"
                    :href="`/trips/${trip.id}`"
                    class="group overflow-hidden rounded-lg border border-[#d8cdbb] bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-[#3b352d] dark:bg-[#181613]"
                >
                    <div class="h-2 bg-[linear-gradient(90deg,#1b6b6f,#d98935,#b7472a)]" />
                    <div class="space-y-5 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-xs font-medium uppercase text-[#8a5b26]">{{ bucketLabel(trip.bucket) }}</div>
                                <h2 class="mt-1 text-xl font-semibold leading-tight group-hover:text-[#1b6b6f]">{{ trip.name }}</h2>
                            </div>
                            <Share2 v-if="!trip.is_owner || trip.counts.collaborators" class="h-5 w-5 text-[#1b6b6f]" />
                        </div>
                        <div class="space-y-2 text-sm text-[#655c50] dark:text-[#c8beb0]">
                            <div class="flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                {{ trip.destination }}
                            </div>
                            <div class="flex items-center gap-2">
                                <CalendarDays class="h-4 w-4" />
                                {{ formatDate(trip.starts_on) }} - {{ formatDate(trip.ends_on) }} · {{ trip.length }}
                            </div>
                            <div class="flex items-center gap-2">
                                <Plane class="h-4 w-4" />
                                {{ trip.counts.reservations }} reservations · {{ trip.counts.items }} itinerary items
                            </div>
                        </div>
                        <p v-if="trip.summary" class="line-clamp-2 text-sm text-[#655c50] dark:text-[#c8beb0]">{{ trip.summary }}</p>
                    </div>
                </Link>
            </section>

            <section v-else class="rounded-lg border border-dashed border-[#c6b9a7] bg-white p-10 text-center dark:border-[#4a4137] dark:bg-[#181613]">
                <Luggage class="mx-auto h-10 w-10 text-[#1b6b6f]" />
                <h2 class="mt-4 text-xl font-semibold">No trips yet</h2>
                <p class="mt-2 text-sm text-[#655c50] dark:text-[#c8beb0]">Create your first trip to start organizing dates, stays, flights, and shared details.</p>
            </section>
        </div>
    </div>
</template>
