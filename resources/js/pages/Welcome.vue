<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    FileText,
    Luggage,
    MapPin,
    Plane,
    Share2,
} from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const features = [
    {
        icon: CalendarDays,
        title: 'Dates that stay clear',
        body: 'Keep flights, stays, activities, and reminders in one time-aware plan.',
    },
    {
        icon: Share2,
        title: 'Built for two',
        body: 'Invite your travel partner as a viewer or editor and plan from the same source.',
    },
    {
        icon: FileText,
        title: 'Details at hand',
        body: 'Confirmation numbers, documents, tasks, packing lists, and costs stay with the trip.',
    },
];

const itinerary = [
    ['08:30', 'Flight DEN to HND', 'United UA143'],
    ['15:00', 'Hotel check-in', 'Shinjuku stay'],
    ['19:30', 'Dinner reservation', 'Golden Gai'],
];
</script>

<template>
    <Head title="Vacation Plan">
        <meta
            name="description"
            content="A simple shared vacation planner for trips, dates, reservations, documents, packing, and reminders."
        />
    </Head>

    <main class="min-h-screen overflow-hidden bg-[#f7f3ec] text-[#211f1a] dark:bg-[#11100e] dark:text-[#f3efe7]">
        <section class="relative">
            <div class="absolute inset-x-0 top-0 h-2 bg-[linear-gradient(90deg,#1b6b6f,#d98935,#b7472a,#223843)]" />
            <div class="mx-auto grid min-h-screen w-full max-w-7xl gap-10 px-5 py-6 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:py-10">
                <div class="flex min-h-[calc(100vh-3rem)] flex-col">
                    <header class="flex items-center justify-between gap-4">
                        <Link :href="dashboard()" class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-[#1b6b6f] text-white shadow-sm">
                                <Luggage class="size-5" />
                            </span>
                            <span class="text-sm font-semibold uppercase tracking-[0.18em] text-[#51483d] dark:text-[#d6cec2]">
                                Vacation Plan
                            </span>
                        </Link>

                        <nav class="flex items-center gap-2 text-sm">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="dashboard()"
                                class="rounded-md border border-[#cfc2af] px-3 py-2 font-medium hover:border-[#1b6b6f] dark:border-[#3b352d]"
                            >
                                Dashboard
                            </Link>
                            <template v-else>
                                <Link :href="login()" class="rounded-md px-3 py-2 font-medium text-[#51483d] hover:text-[#1b6b6f] dark:text-[#d6cec2]">
                                    Log in
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="register()"
                                    class="rounded-md bg-[#1b6b6f] px-4 py-2 font-medium text-white shadow-sm hover:bg-[#155356]"
                                >
                                    Sign up
                                </Link>
                            </template>
                        </nav>
                    </header>

                    <div class="flex flex-1 flex-col justify-center py-16 lg:py-24">
                        <div class="max-w-3xl">
                            <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-[#cfc2af] bg-white/60 px-3 py-1 text-xs font-medium uppercase tracking-[0.16em] text-[#6d6253] shadow-sm dark:border-[#3b352d] dark:bg-white/5 dark:text-[#c8beb0]">
                                <Plane class="size-3.5" />
                                Shared travel planning
                            </div>
                            <h1 class="max-w-3xl text-5xl font-semibold leading-[0.98] tracking-normal sm:text-6xl lg:text-7xl">
                                Plan trips without losing the details.
                            </h1>
                            <p class="mt-6 max-w-xl text-base leading-7 text-[#655c50] dark:text-[#c8beb0]">
                                A calm place for flights, hotels, itinerary days, packing, budget, reminders, and the confirmations you always need right when you are walking out the door.
                            </p>

                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    v-if="$page.props.auth.user"
                                    :href="dashboard()"
                                    class="inline-flex h-11 items-center justify-center rounded-md bg-[#1b6b6f] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#155356]"
                                >
                                    Open trips
                                </Link>
                                <template v-else>
                                    <Link
                                        v-if="canRegister"
                                        :href="register()"
                                        class="inline-flex h-11 items-center justify-center rounded-md bg-[#1b6b6f] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#155356]"
                                    >
                                        Start planning
                                    </Link>
                                    <Link
                                        :href="login()"
                                        class="inline-flex h-11 items-center justify-center rounded-md border border-[#cfc2af] bg-white/50 px-5 text-sm font-semibold text-[#211f1a] hover:border-[#1b6b6f] dark:border-[#3b352d] dark:bg-white/5 dark:text-[#f3efe7]"
                                    >
                                        Log in
                                    </Link>
                                </template>
                            </div>
                        </div>

                        <div class="mt-12 grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="feature in features"
                                :key="feature.title"
                                class="rounded-lg border border-[#d8cdbb] bg-white/70 p-4 shadow-sm dark:border-[#3b352d] dark:bg-white/5"
                            >
                                <component :is="feature.icon" class="size-5 text-[#1b6b6f]" />
                                <h2 class="mt-3 text-sm font-semibold">{{ feature.title }}</h2>
                                <p class="mt-2 text-sm leading-6 text-[#655c50] dark:text-[#c8beb0]">{{ feature.body }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative pb-12 lg:pb-0">
                    <div class="absolute -left-8 top-12 hidden h-40 w-40 rounded-full border border-[#d98935]/40 lg:block" />
                    <div class="relative rounded-lg border border-[#d8cdbb] bg-[#fffaf1] p-4 shadow-xl shadow-[#8b7656]/10 dark:border-[#3b352d] dark:bg-[#181613]">
                        <div class="rounded-md bg-[#223843] p-5 text-white">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-[#c9d8d6]">Upcoming</p>
                                    <h2 class="mt-2 text-2xl font-semibold">Tokyo in October</h2>
                                    <p class="mt-1 flex items-center gap-2 text-sm text-[#c9d8d6]">
                                        <MapPin class="size-4" />
                                        Denver to Tokyo
                                    </p>
                                </div>
                                <div class="rounded-md bg-white/10 px-3 py-2 text-right">
                                    <div class="text-xl font-semibold">9</div>
                                    <div class="text-xs text-[#c9d8d6]">days</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3">
                            <div class="rounded-md border border-[#e2d7c6] bg-white p-4 dark:border-[#3b352d] dark:bg-[#11100e]">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">Travel day</h3>
                                    <span class="rounded-full bg-[#eaf4f1] px-2 py-1 text-xs font-medium text-[#1b6b6f] dark:bg-[#1b6b6f]/20">Ready</span>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <div
                                        v-for="item in itinerary"
                                        :key="item[0]"
                                        class="grid grid-cols-[3.25rem_1fr] gap-3 rounded-md bg-[#f7f3ec] p-3 text-sm dark:bg-[#181613]"
                                    >
                                        <div class="font-semibold text-[#8a5b26]">{{ item[0] }}</div>
                                        <div>
                                            <div class="font-medium">{{ item[1] }}</div>
                                            <div class="text-[#655c50] dark:text-[#c8beb0]">{{ item[2] }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-md border border-[#e2d7c6] bg-white p-4 dark:border-[#3b352d] dark:bg-[#11100e]">
                                    <CheckCircle2 class="size-5 text-[#1b6b6f]" />
                                    <div class="mt-3 text-sm font-semibold">Shared with Emma</div>
                                    <div class="mt-1 text-xs text-[#655c50] dark:text-[#c8beb0]">Editor access</div>
                                </div>
                                <div class="rounded-md border border-[#e2d7c6] bg-white p-4 dark:border-[#3b352d] dark:bg-[#11100e]">
                                    <FileText class="size-5 text-[#b7472a]" />
                                    <div class="mt-3 text-sm font-semibold">6 documents</div>
                                    <div class="mt-1 text-xs text-[#655c50] dark:text-[#c8beb0]">Tickets and stays</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
