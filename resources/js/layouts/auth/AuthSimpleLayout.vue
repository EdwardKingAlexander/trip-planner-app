<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Luggage, Plane, Share2 } from 'lucide-vue-next';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="grid min-h-svh bg-[#f7f3ec] text-[#211f1a] lg:grid-cols-[0.95fr_1.05fr] dark:bg-[#11100e] dark:text-[#f3efe7]"
    >
        <section class="relative hidden overflow-hidden border-r border-[#d8cdbb] bg-[#223843] p-10 text-white lg:flex lg:flex-col lg:justify-between dark:border-[#3b352d]">
            <div class="absolute inset-x-0 top-0 h-2 bg-[linear-gradient(90deg,#1b6b6f,#d98935,#b7472a)]" />
            <Link :href="home()" class="relative flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-lg bg-white/10">
                    <Luggage class="size-5" />
                </span>
                <span class="text-sm font-semibold uppercase tracking-[0.18em] text-[#d7e1df]">Vacation Plan</span>
            </Link>

            <div class="relative max-w-md">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1 text-xs font-medium uppercase tracking-[0.16em] text-[#d7e1df]">
                    <Plane class="size-3.5" />
                    Travel ready
                </div>
                <h2 class="text-5xl font-semibold leading-tight">Every trip detail, ready when you are.</h2>
                <p class="mt-5 text-sm leading-6 text-[#d7e1df]">
                    Shared planning for flights, stays, itinerary days, documents, reminders, packing, and costs.
                </p>
            </div>

            <div class="relative grid gap-3">
                <div class="rounded-lg border border-white/15 bg-white/10 p-4">
                    <div class="flex items-center gap-3 text-sm font-medium">
                        <CalendarDays class="size-5 text-[#f3b562]" />
                        Dates, times, and time zones
                    </div>
                </div>
                <div class="rounded-lg border border-white/15 bg-white/10 p-4">
                    <div class="flex items-center gap-3 text-sm font-medium">
                        <Share2 class="size-5 text-[#8fd3cf]" />
                        Shared access for your travel partner
                    </div>
                </div>
            </div>
        </section>

        <section class="flex min-h-svh items-center justify-center p-6 sm:p-8">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center gap-4 text-center lg:hidden">
                    <Link
                        :href="home()"
                        class="flex items-center gap-3 font-medium"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-[#1b6b6f] text-white"
                        >
                            <Luggage class="size-5" />
                        </div>
                        <span class="text-sm font-semibold uppercase tracking-[0.18em] text-[#51483d] dark:text-[#d6cec2]">Vacation Plan</span>
                    </Link>
                </div>

                <div class="rounded-lg border border-[#d8cdbb] bg-white/85 p-6 shadow-xl shadow-[#8b7656]/10 backdrop-blur sm:p-8 dark:border-[#3b352d] dark:bg-[#181613]/90">
                    <div class="mb-7 space-y-2 text-center">
                        <h1 class="text-2xl font-semibold">{{ title }}</h1>
                        <p class="text-sm leading-6 text-[#655c50] dark:text-[#c8beb0]">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </section>
    </div>
</template>
