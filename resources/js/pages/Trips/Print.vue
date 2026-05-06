<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, FileText, Luggage, Plane, Printer } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

type Trip = Record<string, any>;

defineProps<{ trip: Trip }>();

const formatDate = (value: string | null) => value
    ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value))
    : 'Flexible';

const formatDateTime = (value: string | null) => value
    ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value))
    : 'Time TBD';

const printPage = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${trip.name} Print Summary`" />

    <main class="min-h-screen bg-[#f7f3ec] p-5 text-[#211f1a] print:bg-white print:p-0">
        <div class="mx-auto max-w-5xl rounded-lg border border-[#d8cdbb] bg-white p-8 shadow-sm print:border-0 print:shadow-none">
            <header class="flex flex-col gap-5 border-b border-[#e2d7c6] pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link href="/trips" class="print:hidden text-sm font-medium text-[#1b6b6f]">Back to trips</Link>
                    <h1 class="mt-3 text-4xl font-semibold">{{ trip.name }}</h1>
                    <p class="mt-2 text-[#655c50]">{{ trip.destination }} · {{ formatDate(trip.starts_on) }} - {{ formatDate(trip.ends_on) }}</p>
                    <p v-if="trip.summary" class="mt-4 max-w-3xl text-sm leading-6 text-[#655c50]">{{ trip.summary }}</p>
                </div>
                <Button class="print:hidden bg-[#1b6b6f] hover:bg-[#155356]" @click="printPage">
                    <Printer class="h-4 w-4" />
                    Print
                </Button>
            </header>

            <section class="mt-8 grid gap-4 sm:grid-cols-4">
                <div class="rounded-md bg-[#f7f3ec] p-4">
                    <CalendarDays class="h-5 w-5 text-[#1b6b6f]" />
                    <div class="mt-2 text-2xl font-semibold">{{ trip.days.length }}</div>
                    <div class="text-xs text-[#655c50]">Days</div>
                </div>
                <div class="rounded-md bg-[#f7f3ec] p-4">
                    <Plane class="h-5 w-5 text-[#1b6b6f]" />
                    <div class="mt-2 text-2xl font-semibold">{{ trip.reservations.length }}</div>
                    <div class="text-xs text-[#655c50]">Reservations</div>
                </div>
                <div class="rounded-md bg-[#f7f3ec] p-4">
                    <Luggage class="h-5 w-5 text-[#1b6b6f]" />
                    <div class="mt-2 text-2xl font-semibold">{{ trip.packing_items.length }}</div>
                    <div class="text-xs text-[#655c50]">Packing</div>
                </div>
                <div class="rounded-md bg-[#f7f3ec] p-4">
                    <FileText class="h-5 w-5 text-[#1b6b6f]" />
                    <div class="mt-2 text-2xl font-semibold">{{ trip.documents.length }}</div>
                    <div class="text-xs text-[#655c50]">Documents</div>
                </div>
            </section>

            <section class="mt-8 space-y-5">
                <h2 class="text-xl font-semibold">Itinerary</h2>
                <div v-for="day in trip.days" :key="day.id" class="break-inside-avoid rounded-md border border-[#e2d7c6] p-4">
                    <h3 class="font-semibold">{{ day.title }} · {{ formatDate(day.date) }}</h3>
                    <div class="mt-3 space-y-2">
                        <div v-for="item in day.itinerary_items" :key="item.id" class="grid grid-cols-[8rem_1fr] gap-3 text-sm">
                            <div class="text-[#8a5b26]">{{ formatDateTime(item.starts_at) }}</div>
                            <div>
                                <div class="font-medium">{{ item.title }}</div>
                                <div class="text-[#655c50]">{{ item.location_name || item.type }}</div>
                            </div>
                        </div>
                        <p v-if="!day.itinerary_items.length" class="text-sm text-[#655c50]">No itinerary items.</p>
                    </div>
                </div>
            </section>

            <section class="mt-8 grid gap-6 lg:grid-cols-2">
                <div class="space-y-3">
                    <h2 class="text-xl font-semibold">Reservations</h2>
                    <div v-for="reservation in trip.reservations" :key="reservation.id" class="break-inside-avoid rounded-md border border-[#e2d7c6] p-4 text-sm">
                        <div class="font-semibold">{{ reservation.title }}</div>
                        <div class="text-[#655c50]">{{ reservation.provider_name || reservation.type }} · {{ reservation.booking_reference || 'No confirmation' }}</div>
                        <div class="mt-2">{{ formatDateTime(reservation.starts_at) }} - {{ formatDateTime(reservation.ends_at) }}</div>
                    </div>
                </div>
                <div class="space-y-3">
                    <h2 class="text-xl font-semibold">Tasks & Documents</h2>
                    <div v-for="task in trip.tasks" :key="task.id" class="rounded-md border border-[#e2d7c6] p-3 text-sm">
                        {{ task.title }} · {{ task.priority }}
                    </div>
                    <div v-for="document in trip.documents" :key="document.id" class="rounded-md border border-[#e2d7c6] p-3 text-sm">
                        {{ document.title }} · {{ document.document_type }}
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
