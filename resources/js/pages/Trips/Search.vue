<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileText, Luggage, Plane, Search } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Trip = Record<string, any>;

const props = defineProps<{
    query: string;
    trips: Trip[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: '/trips' },
            { title: 'Search', href: '/trips/search' },
        ],
    },
});

const form = useForm({ q: props.query ?? '' });

const submit = () => {
    router.get('/trips/search', { q: form.q || undefined }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Trip Search" />

    <div class="min-h-full min-w-0 overflow-x-clip bg-[#f7f3ec] p-4 text-[#211f1a] sm:p-6 lg:p-8 dark:bg-[#11100e] dark:text-[#f3efe7]">
        <div class="mx-auto min-w-0 max-w-5xl space-y-6">
            <header class="rounded-lg border border-[#d8cdbb] bg-white p-6 dark:border-[#3b352d] dark:bg-[#181613]">
                <div class="flex items-center gap-2 text-sm font-medium uppercase tracking-[0.16em] text-[#1b6b6f]">
                    <Search class="h-4 w-4" />
                    Global search
                </div>
                <h1 class="mt-3 text-3xl font-semibold">Find anything in your trips.</h1>
                <form class="mt-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="submit">
                    <Input v-model="form.q" class="h-11" placeholder="Search trips, confirmations, documents, tasks..." />
                    <Button type="submit" class="bg-[#1b6b6f] hover:bg-[#155356]">Search</Button>
                </form>
            </header>

            <section class="space-y-3">
                <Link
                    v-for="trip in trips"
                    :key="trip.id"
                    :href="`/trips/${trip.id}`"
                    class="block rounded-lg border border-[#d8cdbb] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-[#3b352d] dark:bg-[#181613]"
                >
                    <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-sm font-medium text-[#1b6b6f]">
                                <Luggage class="h-4 w-4" />
                                {{ trip.destination }}
                            </div>
                            <h2 class="mt-2 text-xl font-semibold">{{ trip.name }}</h2>
                            <p class="mt-1 text-sm text-[#655c50] dark:text-[#c8beb0]">{{ trip.starts_on }} - {{ trip.ends_on }}</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs text-[#655c50]">
                            <div class="rounded-md bg-[#f0e7d9] p-2 dark:bg-[#24201b]"><Plane class="mx-auto mb-1 h-4 w-4" />{{ trip.reservations.length }}</div>
                            <div class="rounded-md bg-[#f0e7d9] p-2 dark:bg-[#24201b]"><FileText class="mx-auto mb-1 h-4 w-4" />{{ trip.documents.length }}</div>
                            <div class="rounded-md bg-[#f0e7d9] p-2 dark:bg-[#24201b]">{{ trip.tasks.length }} tasks</div>
                        </div>
                    </div>
                </Link>

                <div v-if="query && !trips.length" class="rounded-lg border border-dashed border-[#c6b9a7] bg-white p-10 text-center dark:border-[#4a4137] dark:bg-[#181613]">
                    <Search class="mx-auto h-8 w-8 text-[#1b6b6f]" />
                    <h2 class="mt-3 text-lg font-semibold">No matches</h2>
                    <p class="mt-2 text-sm text-[#655c50] dark:text-[#c8beb0]">Try another destination, confirmation number, document, or task.</p>
                </div>
            </section>
        </div>
    </div>
</template>
