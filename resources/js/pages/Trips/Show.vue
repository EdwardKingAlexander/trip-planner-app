<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Bell,
    CalendarClock,
    CheckSquare,
    Download,
    DollarSign,
    FileText,
    Hotel,
    ListChecks,
    Plane,
    Printer,
    Share2,
    Sparkles,
    Upload,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { store as storeCost, update as updateCost } from '@/routes/trips/costs';
import { store as storeDocument, update as updateDocument } from '@/routes/trips/documents';
import { store as storeItineraryItem, update as updateItineraryItem } from '@/routes/trips/itinerary-items';
import { store as storePackingItem, update as updatePackingItem } from '@/routes/trips/packing-items';
import { store as storeReminder, update as updateReminder } from '@/routes/trips/reminders';
import { store as storeReservation, update as updateReservation } from '@/routes/trips/reservations';
import { store as storeTask, update as updateTask } from '@/routes/trips/tasks';

type Trip = {
    id: number;
    name: string;
    destination: string;
    starts_on: string;
    ends_on: string;
    status: string;
    summary: string | null;
    length: string;
    can_edit: boolean;
    can_share: boolean;
    days: Array<{
        id: number;
        date: string;
        title: string | null;
        items: Array<{
            id: number;
            type: string;
            title: string;
            description: string | null;
            location_name: string | null;
            starts_at: string | null;
            ends_at: string | null;
            timezone: string;
            status: string;
            is_all_day: boolean;
        }>;
    }>;
    reservations: Array<Record<string, any>>;
    costs: Array<Record<string, any>>;
    packing_items: Array<Record<string, any>>;
    tasks: Array<Record<string, any>>;
    documents: Array<Record<string, any>>;
    reminders: Array<Record<string, any>>;
    collaborators: Array<Record<string, any>>;
    import_batches: Array<Record<string, any>>;
    automation_suggestions: Array<Record<string, any>>;
};

const props = defineProps<{ trip: Trip }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: '/trips' },
            { title: 'Trip', href: '#' },
        ],
    },
});

const activePanel = ref('itinerary');
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
type EditKind = 'itinerary' | 'reservation' | 'cost' | 'packing' | 'task' | 'document' | 'reminder';
const editing = ref<{ type: EditKind; id: number } | null>(null);
const editData = ref<Record<string, any>>({});

const itineraryForm = useForm({
    trip_day_id: props.trip.days[0]?.id ?? null,
    type: 'activity',
    title: '',
    description: '',
    location_name: '',
    starts_at: '',
    ends_at: '',
    timezone,
    is_all_day: false,
    status: 'planned',
});

const reservationForm = useForm({
    type: 'flight',
    title: '',
    provider_name: '',
    booking_reference: '',
    status: 'reserved',
    starts_at: '',
    starts_timezone: timezone,
    ends_at: '',
    ends_timezone: timezone,
    location_name: '',
    address: '',
    contact_phone: '',
    contact_email: '',
    notes: '',
    airline: '',
    flight_number: '',
    departure_airport: '',
    arrival_airport: '',
    property_name: '',
    room_type: '',
});

const costForm = useForm({
    category: 'lodging',
    label: '',
    planned_amount: '',
    actual_amount: '',
    currency: 'USD',
    notes: '',
});

const packingForm = useForm({
    traveler_name: '',
    category: 'clothes',
    label: '',
    quantity: 1,
    notes: '',
});

const taskForm = useForm({
    title: '',
    description: '',
    due_at: '',
    priority: 'normal',
});

const documentForm = useForm({
    title: '',
    document_type: 'confirmation',
    expires_on: '',
    notes: '',
});

const reminderForm = useForm({
    label: '',
    remind_at: '',
    timezone,
    notes: '',
});

const collaboratorForm = useForm({
    email: '',
    role: 'editor',
});

const importForm = useForm({
    source_type: 'confirmation',
    raw_text: '',
});

const panels = [
    { id: 'itinerary', label: 'Itinerary', icon: CalendarClock },
    { id: 'reservations', label: 'Reservations', icon: Plane },
    { id: 'budget', label: 'Budget', icon: DollarSign },
    { id: 'packing', label: 'Packing', icon: ListChecks },
    { id: 'tasks', label: 'Tasks', icon: CheckSquare },
    { id: 'documents', label: 'Documents', icon: FileText },
    { id: 'imports', label: 'Imports', icon: Upload },
    { id: 'sharing', label: 'Sharing', icon: Share2 },
];

const plannedTotal = computed(() => props.trip.costs.reduce((sum, cost) => sum + Number(cost.planned_amount ?? 0), 0));
const actualTotal = computed(() => props.trip.costs.reduce((sum, cost) => sum + Number(cost.actual_amount ?? 0), 0));
const completedTasks = computed(() => props.trip.tasks.filter((task) => task.completed_at).length);
const isShared = computed(() => (props.trip.collaborators?.length ?? 0) > 0);

const post = (form: ReturnType<typeof useForm>, url: string, resetFields?: string[]) => {
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => resetFields ? form.reset(...resetFields) : form.reset(),
    });
};

const isEditing = (type: EditKind, id: number) => editing.value?.type === type && editing.value.id === id;

const toDateTimeLocal = (value: string | null) => value ? new Date(value).toISOString().slice(0, 16) : '';

const startEdit = (type: EditKind, entry: Record<string, any>) => {
    const normalized = { ...entry };

    ['starts_at', 'ends_at', 'due_at', 'remind_at', 'completed_at'].forEach((key) => {
        if (key in normalized) {
            normalized[key] = toDateTimeLocal(normalized[key]);
        }
    });

    editing.value = { type, id: entry.id };
    editData.value = normalized;
};

const cancelEdit = () => {
    editing.value = null;
    editData.value = {};
};

const patchEdit = (url: string) => {
    router.patch(url, editData.value, {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
};

const setTaskCompletion = (event: Event) => {
    editData.value.completed_at = (event.target as HTMLInputElement).checked ? new Date().toISOString().slice(0, 16) : null;
};

const destroyCollaborator = (id: number) => {
    router.delete(`/trips/${props.trip.id}/collaborators/${id}`, { preserveScroll: true });
};

const simplePost = (url: string) => {
    router.post(url, {}, { preserveScroll: true });
};

const formatDate = (value: string | null) => value
    ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value))
    : 'Flexible';

const formatDateTime = (value: string | null, timeZone?: string) => value
    ? new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timeZone || undefined,
    }).format(new Date(value))
    : 'Time TBD';
</script>

<template>
    <Head :title="trip.name" />

    <div class="travel-page">
        <div class="travel-container">
            <header class="travel-hero">
                <div class="travel-stripe" />
                <div class="grid gap-6 p-6 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div>
                        <Link href="/trips" class="inline-flex min-h-11 items-center text-sm font-medium text-[#0f777f]">Back to trips</Link>
                        <h1 class="mt-3 text-3xl font-semibold sm:text-5xl">{{ trip.name }}</h1>
                        <p class="travel-muted mt-2 text-sm">
                            {{ trip.destination }} · {{ formatDate(trip.starts_on) }} - {{ formatDate(trip.ends_on) }} · {{ trip.length }}
                        </p>
                        <p v-if="trip.summary" class="travel-muted mt-4 max-w-3xl text-sm leading-6">{{ trip.summary }}</p>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2 rounded-lg border border-[#c8dde0] bg-white/85 p-3 text-center shadow-xs dark:border-[#25414a] dark:bg-white/5">
                            <div class="rounded-md bg-[#ecf8f5] p-2 dark:bg-[#143039]">
                                <div class="text-2xl font-semibold">{{ trip.reservations.length }}</div>
                                <div class="text-xs text-[#52666b] dark:text-[#b8d5d2]">Bookings</div>
                            </div>
                            <div class="rounded-md bg-[#fff4df] p-2 dark:bg-[#332819]">
                                <div class="text-2xl font-semibold">{{ trip.days.length }}</div>
                                <div class="text-xs text-[#52666b] dark:text-[#f2d6a8]">Days</div>
                            </div>
                            <div class="rounded-md bg-[#eef3ff] p-2 dark:bg-[#17243b]">
                                <div class="text-2xl font-semibold">{{ trip.collaborators.length }}</div>
                                <div class="text-xs text-[#52666b] dark:text-[#bccfff]">Shared</div>
                            </div>
                        </div>
                        <div class="grid gap-2 min-[380px]:grid-cols-3">
                            <Button as-child class="travel-touch" variant="outline" size="sm">
                                <a :href="`/trips/${trip.id}/print`"><Printer class="h-4 w-4" /> Print</a>
                            </Button>
                            <Button as-child class="travel-touch" variant="outline" size="sm">
                                <a :href="`/trips/${trip.id}/export.ics`"><CalendarClock class="h-4 w-4" /> ICS</a>
                            </Button>
                            <Button as-child class="travel-touch" variant="outline" size="sm">
                                <a :href="`/trips/${trip.id}/export.json`"><Download class="h-4 w-4" /> JSON</a>
                            </Button>
                        </div>
                    </div>
                </div>
            </header>

            <nav class="travel-panel grid grid-cols-2 gap-2 p-2 sm:grid-cols-4 xl:flex">
                <button
                    v-for="panel in panels"
                    :key="panel.id"
                    type="button"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium xl:justify-start"
                    :class="activePanel === panel.id ? 'bg-[#0f777f] text-white shadow-sm' : 'text-[#52666b] hover:bg-[#e4f5f6] dark:text-[#b8d5d2] dark:hover:bg-[#183640]'"
                    @click="activePanel = panel.id"
                >
                    <component :is="panel.icon" class="h-4 w-4" />
                    {{ panel.label }}
                </button>
            </nav>

            <section v-if="activePanel === 'itinerary'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <div class="space-y-4">
                    <Card v-for="day in trip.days" :key="day.id" class="travel-panel">
                        <CardHeader>
                            <CardTitle class="text-base">{{ day.title }} · {{ formatDate(day.date) }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="day.items.length" class="space-y-2">
                                <div v-for="item in day.items" :key="item.id" class="rounded-md border border-[#d8eaec] p-3 dark:border-[#25414a]">
                                    <template v-if="isEditing('itinerary', item.id)">
                                        <form class="grid gap-3" @submit.prevent="patchEdit(updateItineraryItem.url({ trip: trip.id, itineraryItem: item.id }))">
                                            <select v-model="editData.trip_day_id" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                                <option :value="null">Unscheduled idea</option>
                                                <option v-for="optionDay in trip.days" :key="optionDay.id" :value="optionDay.id">{{ optionDay.title }} · {{ optionDay.date }}</option>
                                            </select>
                                            <Input class="travel-touch" v-model="editData.title" placeholder="Title" />
                                            <Input class="travel-touch" v-model="editData.location_name" placeholder="Location" />
                                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                                <Input class="travel-touch" v-model="editData.starts_at" type="datetime-local" />
                                                <Input class="travel-touch" v-model="editData.ends_at" type="datetime-local" />
                                            </div>
                                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                                <Input class="travel-touch" v-model="editData.timezone" placeholder="Timezone" />
                                                <select v-model="editData.status" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                                    <option value="idea">Idea</option>
                                                    <option value="planned">Planned</option>
                                                    <option value="booked">Booked</option>
                                                    <option value="cancelled">Cancelled</option>
                                                    <option value="completed">Completed</option>
                                                </select>
                                            </div>
                                            <select v-model="editData.type" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                                <option value="activity">Activity</option>
                                                <option value="dining">Dining</option>
                                                <option value="transport">Transport</option>
                                                <option value="note">Note</option>
                                                <option value="custom">Custom</option>
                                            </select>
                                            <textarea v-model="editData.description" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                            <div class="flex gap-2">
                                                <Button size="sm" class="travel-button-primary">Save</Button>
                                                <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                            </div>
                                        </form>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold">{{ item.title }}</div>
                                                <div class="text-xs text-[#52666b] dark:text-[#b8d5d2]">{{ item.type }} · {{ formatDateTime(item.starts_at, item.timezone) }}</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-[#e4f5f6] px-2 py-1 text-xs dark:bg-[#183640]">{{ item.status }}</span>
                                                <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('itinerary', { ...item, trip_day_id: day.id })">Edit</Button>
                                            </div>
                                        </div>
                                        <p v-if="item.location_name" class="mt-2 text-sm text-[#52666b] dark:text-[#b8d5d2]">{{ item.location_name }}</p>
                                        <p v-if="item.description" class="mt-2 rounded-md bg-[#f6fbfb] p-2 text-sm text-[#52666b] dark:bg-[#102a32] dark:text-[#b8d5d2]">{{ item.description }}</p>
                                        <p v-if="isShared && item.last_edited_by" class="mt-2 text-xs italic text-[#52666b] dark:text-[#b8d5d2]">Last edited by {{ item.last_edited_by }}</p>
                                    </template>
                                </div>
                            </div>
                            <p v-else class="text-sm text-[#52666b] dark:text-[#b8d5d2]">No items planned for this day yet.</p>
                        </CardContent>
                    </Card>
                </div>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Itinerary Item</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(itineraryForm, storeItineraryItem.url(trip.id), ['title', 'description', 'location_name', 'starts_at', 'ends_at'])">
                            <select v-model="itineraryForm.trip_day_id" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option :value="null">Unscheduled idea</option>
                                <option v-for="day in trip.days" :key="day.id" :value="day.id">{{ day.title }} · {{ day.date }}</option>
                            </select>
                            <Input class="travel-touch" v-model="itineraryForm.title" placeholder="Title" />
                            <InputError :message="itineraryForm.errors.title" />
                            <Input class="travel-touch" v-model="itineraryForm.location_name" placeholder="Location" />
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <Input class="travel-touch" v-model="itineraryForm.starts_at" type="datetime-local" />
                                <Input class="travel-touch" v-model="itineraryForm.ends_at" type="datetime-local" />
                            </div>
                            <Input class="travel-touch" v-model="itineraryForm.timezone" placeholder="Timezone" />
                            <select v-model="itineraryForm.type" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="activity">Activity</option>
                                <option value="dining">Dining</option>
                                <option value="transport">Transport</option>
                                <option value="note">Note</option>
                                <option value="custom">Custom</option>
                            </select>
                            <textarea v-model="itineraryForm.description" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button class="travel-button-primary">Add item</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'reservations'" class="grid gap-4 lg:grid-cols-[1fr_24rem]">
                <div class="grid gap-3">
                    <Card v-for="reservation in trip.reservations" :key="reservation.id" class="travel-panel">
                        <CardContent class="pt-6">
                            <template v-if="isEditing('reservation', reservation.id)">
                                <form class="grid gap-3" @submit.prevent="patchEdit(updateReservation.url({ trip: trip.id, reservation: reservation.id }))">
                                    <select v-model="editData.type" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                        <option value="flight">Flight</option>
                                        <option value="lodging">Hotel / lodging</option>
                                        <option value="transport">Ground transport</option>
                                        <option value="activity">Activity</option>
                                        <option value="dining">Dining</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                    <Input class="travel-touch" v-model="editData.title" placeholder="Reservation title" />
                                    <Input class="travel-touch" v-model="editData.provider_name" placeholder="Provider" />
                                    <Input class="travel-touch" v-model="editData.booking_reference" placeholder="Confirmation number" />
                                    <div class="grid gap-3 min-[460px]:grid-cols-2">
                                        <Input class="travel-touch" v-model="editData.starts_at" type="datetime-local" />
                                        <Input class="travel-touch" v-model="editData.ends_at" type="datetime-local" />
                                    </div>
                                    <div class="grid gap-3 min-[460px]:grid-cols-2">
                                        <Input class="travel-touch" v-model="editData.starts_timezone" placeholder="Start timezone" />
                                        <Input class="travel-touch" v-model="editData.ends_timezone" placeholder="End timezone" />
                                    </div>
                                    <select v-model="editData.status" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                        <option value="researching">Researching</option>
                                        <option value="reserved">Reserved</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="checked_in">Checked in</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                    <template v-if="editData.type === 'flight'">
                                        <div class="grid gap-3 min-[460px]:grid-cols-2">
                                            <Input class="travel-touch" v-model="editData.airline" placeholder="Airline" />
                                            <Input class="travel-touch" v-model="editData.flight_number" placeholder="Flight #" />
                                        </div>
                                        <div class="grid gap-3 min-[460px]:grid-cols-2">
                                            <Input class="travel-touch" v-model="editData.departure_airport" placeholder="From airport" />
                                            <Input class="travel-touch" v-model="editData.arrival_airport" placeholder="To airport" />
                                        </div>
                                    </template>
                                    <template v-if="editData.type === 'lodging'">
                                        <Input class="travel-touch" v-model="editData.property_name" placeholder="Property name" />
                                        <Input class="travel-touch" v-model="editData.room_type" placeholder="Room type" />
                                    </template>
                                    <Input class="travel-touch" v-model="editData.location_name" placeholder="Location" />
                                    <Input class="travel-touch" v-model="editData.address" placeholder="Address" />
                                    <div class="grid gap-3 min-[460px]:grid-cols-2">
                                        <Input class="travel-touch" v-model="editData.contact_phone" placeholder="Phone" />
                                        <Input class="travel-touch" v-model="editData.contact_email" placeholder="Email" />
                                    </div>
                                    <textarea v-model="editData.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                    <div class="flex gap-2">
                                        <Button size="sm" class="travel-button-primary">Save</Button>
                                        <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                    </div>
                                </form>
                            </template>
                            <template v-else>
                                <div class="flex items-start gap-3">
                                    <Plane v-if="reservation.type === 'flight'" class="mt-1 h-5 w-5 text-[#0f777f]" />
                                    <Hotel v-else class="mt-1 h-5 w-5 text-[#8a5b26]" />
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold">{{ reservation.title }}</div>
                                        <div class="text-sm text-[#52666b] dark:text-[#b8d5d2]">{{ reservation.provider_name || reservation.type }} · {{ reservation.booking_reference || 'No confirmation yet' }}</div>
                                        <div class="mt-2 text-sm">{{ formatDateTime(reservation.starts_at, reservation.starts_timezone) }} - {{ formatDateTime(reservation.ends_at, reservation.ends_timezone) }}</div>
                                        <p v-if="reservation.address" class="mt-2 text-sm text-[#52666b] dark:text-[#b8d5d2]">{{ reservation.address }}</p>
                                        <p v-if="reservation.notes" class="mt-2 rounded-md bg-[#f6fbfb] p-2 text-sm text-[#52666b] dark:bg-[#102a32] dark:text-[#b8d5d2]">{{ reservation.notes }}</p>
                                        <p v-if="isShared && reservation.last_edited_by" class="mt-2 text-xs italic text-[#52666b] dark:text-[#b8d5d2]">Last edited by {{ reservation.last_edited_by }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-[#e4f5f6] px-2 py-1 text-xs dark:bg-[#183640]">{{ reservation.status }}</span>
                                        <Button
                                            v-if="trip.can_edit"
                                            size="sm"
                                            type="button"
                                            variant="outline"
                                            class="travel-touch"
                                            @click="startEdit('reservation', {
                                                ...reservation,
                                                airline: reservation.flight_segments?.[0]?.airline ?? reservation.provider_name ?? '',
                                                flight_number: reservation.flight_segments?.[0]?.flight_number ?? '',
                                                departure_airport: reservation.flight_segments?.[0]?.departure_airport ?? '',
                                                arrival_airport: reservation.flight_segments?.[0]?.arrival_airport ?? '',
                                                property_name: reservation.lodging_stay?.property_name ?? reservation.title,
                                                room_type: reservation.lodging_stay?.room_type ?? '',
                                            })"
                                        >
                                            Edit
                                        </Button>
                                    </div>
                                </div>
                            </template>
                        </CardContent>
                    </Card>
                </div>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Reservation</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(reservationForm, storeReservation.url(trip.id), ['title', 'provider_name', 'booking_reference', 'starts_at', 'ends_at', 'location_name', 'address', 'notes', 'airline', 'flight_number', 'departure_airport', 'arrival_airport', 'property_name', 'room_type'])">
                            <select v-model="reservationForm.type" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="flight">Flight</option>
                                <option value="lodging">Hotel / lodging</option>
                                <option value="transport">Ground transport</option>
                                <option value="activity">Activity</option>
                                <option value="dining">Dining</option>
                                <option value="custom">Custom</option>
                            </select>
                            <Input class="travel-touch" v-model="reservationForm.title" placeholder="Reservation title" />
                            <InputError :message="reservationForm.errors.title" />
                            <Input class="travel-touch" v-model="reservationForm.provider_name" placeholder="Provider" />
                            <Input class="travel-touch" v-model="reservationForm.booking_reference" placeholder="Confirmation number" />
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <Input class="travel-touch" v-model="reservationForm.starts_at" type="datetime-local" />
                                <Input class="travel-touch" v-model="reservationForm.ends_at" type="datetime-local" />
                            </div>
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <Input class="travel-touch" v-model="reservationForm.starts_timezone" placeholder="Start timezone" />
                                <Input class="travel-touch" v-model="reservationForm.ends_timezone" placeholder="End timezone" />
                            </div>
                            <template v-if="reservationForm.type === 'flight'">
                                <div class="grid gap-3 min-[460px]:grid-cols-2">
                                    <Input class="travel-touch" v-model="reservationForm.airline" placeholder="Airline" />
                                    <Input class="travel-touch" v-model="reservationForm.flight_number" placeholder="Flight #" />
                                </div>
                                <div class="grid gap-3 min-[460px]:grid-cols-2">
                                    <Input class="travel-touch" v-model="reservationForm.departure_airport" placeholder="From airport" />
                                    <Input class="travel-touch" v-model="reservationForm.arrival_airport" placeholder="To airport" />
                                </div>
                            </template>
                            <template v-if="reservationForm.type === 'lodging'">
                                <Input class="travel-touch" v-model="reservationForm.property_name" placeholder="Property name" />
                                <Input class="travel-touch" v-model="reservationForm.room_type" placeholder="Room type" />
                            </template>
                            <Input class="travel-touch" v-model="reservationForm.address" placeholder="Address" />
                            <textarea v-model="reservationForm.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button class="travel-button-primary">Add reservation</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'budget'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Budget</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-md bg-[#e4f5f6] p-4 dark:bg-[#183640]">
                                <div class="text-xs uppercase text-[#52666b] dark:text-[#b8d5d2]">Planned</div>
                                <div class="text-2xl font-semibold">${{ plannedTotal.toFixed(2) }}</div>
                            </div>
                            <div class="rounded-md bg-[#e4f5f6] p-4 dark:bg-[#183640]">
                                <div class="text-xs uppercase text-[#52666b] dark:text-[#b8d5d2]">Actual</div>
                                <div class="text-2xl font-semibold">${{ actualTotal.toFixed(2) }}</div>
                            </div>
                        </div>
                        <div v-for="cost in trip.costs" :key="cost.id" class="rounded-md border border-[#d8eaec] p-3 text-sm dark:border-[#25414a]">
                            <form v-if="isEditing('cost', cost.id)" class="grid gap-3" @submit.prevent="patchEdit(updateCost.url({ trip: trip.id, cost: cost.id }))">
                                <Input class="travel-touch" v-model="editData.label" placeholder="Label" />
                                <select v-model="editData.category" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                    <option value="flight">Flight</option>
                                    <option value="lodging">Lodging</option>
                                    <option value="food">Food</option>
                                    <option value="activity">Activity</option>
                                    <option value="transport">Transport</option>
                                    <option value="custom">Custom</option>
                                </select>
                                <div class="grid gap-3 min-[460px]:grid-cols-2">
                                    <Input class="travel-touch" v-model="editData.planned_amount" type="number" min="0" step="0.01" placeholder="Planned" />
                                    <Input class="travel-touch" v-model="editData.actual_amount" type="number" min="0" step="0.01" placeholder="Actual" />
                                </div>
                                <Input class="travel-touch" v-model="editData.currency" maxlength="3" placeholder="USD" />
                                <textarea v-model="editData.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                <div class="flex gap-2">
                                    <Button size="sm" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex justify-between gap-3">
                                    <span>{{ cost.label }} · {{ cost.category }}</span>
                                    <div class="flex items-center gap-2">
                                        <span>{{ cost.currency }} {{ cost.actual_amount || cost.planned_amount || '0.00' }}</span>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('cost', cost)">Edit</Button>
                                    </div>
                                </div>
                                <p v-if="cost.notes" class="mt-2 rounded-md bg-[#f6fbfb] p-2 text-[#52666b] dark:bg-[#102a32] dark:text-[#b8d5d2]">{{ cost.notes }}</p>
                                <p v-if="isShared && cost.last_edited_by" class="mt-2 text-xs italic text-[#52666b] dark:text-[#b8d5d2]">Last edited by {{ cost.last_edited_by }}</p>
                            </template>
                        </div>
                    </CardContent>
                </Card>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Cost</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(costForm, storeCost.url(trip.id), ['label', 'planned_amount', 'actual_amount', 'notes'])">
                            <Input class="travel-touch" v-model="costForm.label" placeholder="Label" />
                            <select v-model="costForm.category" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="flight">Flight</option>
                                <option value="lodging">Lodging</option>
                                <option value="food">Food</option>
                                <option value="activity">Activity</option>
                                <option value="transport">Transport</option>
                                <option value="custom">Custom</option>
                            </select>
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <Input class="travel-touch" v-model="costForm.planned_amount" type="number" min="0" step="0.01" placeholder="Planned" />
                                <Input class="travel-touch" v-model="costForm.actual_amount" type="number" min="0" step="0.01" placeholder="Actual" />
                            </div>
                            <Input class="travel-touch" v-model="costForm.currency" maxlength="3" placeholder="USD" />
                            <textarea v-model="costForm.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button class="travel-button-primary">Add cost</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'packing'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Packing List</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="item in trip.packing_items" :key="item.id" class="rounded-md border border-[#d8eaec] p-3 text-sm dark:border-[#25414a]">
                            <form v-if="isEditing('packing', item.id)" class="grid gap-3" @submit.prevent="patchEdit(updatePackingItem.url({ trip: trip.id, packingItem: item.id }))">
                                <Input class="travel-touch" v-model="editData.label" placeholder="Item" />
                                <Input class="travel-touch" v-model="editData.traveler_name" placeholder="Traveler name" />
                                <div class="grid gap-3 min-[460px]:grid-cols-2">
                                    <Input class="travel-touch" v-model="editData.category" placeholder="Category" />
                                    <Input class="travel-touch" v-model="editData.quantity" type="number" min="1" />
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="editData.is_packed" type="checkbox" class="rounded border-input" />
                                    Packed
                                </label>
                                <textarea v-model="editData.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                <div class="flex gap-2">
                                    <Button size="sm" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex items-center justify-between gap-3">
                                    <span>{{ item.quantity }}x {{ item.label }} <span class="text-[#52666b] dark:text-[#b8d5d2]">· {{ item.category }}</span></span>
                                    <div class="flex items-center gap-2">
                                        <span>{{ item.traveler_name || 'Shared' }}</span>
                                        <span v-if="item.is_packed" class="rounded-full bg-[#e4f5f6] px-2 py-1 text-xs dark:bg-[#183640]">Packed</span>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('packing', item)">Edit</Button>
                                    </div>
                                </div>
                                <p v-if="item.notes" class="mt-2 rounded-md bg-[#f6fbfb] p-2 text-[#52666b] dark:bg-[#102a32] dark:text-[#b8d5d2]">{{ item.notes }}</p>
                                <p v-if="isShared && item.last_edited_by" class="mt-2 text-xs italic text-[#52666b] dark:text-[#b8d5d2]">Last edited by {{ item.last_edited_by }}</p>
                            </template>
                        </div>
                    </CardContent>
                </Card>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Packing Item</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(packingForm, storePackingItem.url(trip.id), ['traveler_name', 'label', 'notes'])">
                            <Input class="travel-touch" v-model="packingForm.label" placeholder="Item" />
                            <Input class="travel-touch" v-model="packingForm.traveler_name" placeholder="Traveler name" />
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <Input class="travel-touch" v-model="packingForm.category" placeholder="Category" />
                                <Input class="travel-touch" v-model="packingForm.quantity" type="number" min="1" />
                            </div>
                            <textarea v-model="packingForm.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button class="travel-button-primary">Add item</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'tasks'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Tasks · {{ completedTasks }}/{{ trip.tasks.length }} done</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="task in trip.tasks" :key="task.id" class="rounded-md border border-[#d8eaec] p-3 text-sm dark:border-[#25414a]">
                            <form v-if="isEditing('task', task.id)" class="grid gap-3" @submit.prevent="patchEdit(updateTask.url({ trip: trip.id, task: task.id }))">
                                <Input class="travel-touch" v-model="editData.title" placeholder="Task" />
                                <Input class="travel-touch" v-model="editData.due_at" type="datetime-local" />
                                <select v-model="editData.priority" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input :checked="Boolean(editData.completed_at)" type="checkbox" class="rounded border-input" @change="setTaskCompletion" />
                                    Complete
                                </label>
                                <textarea v-model="editData.description" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                <div class="flex gap-2">
                                    <Button size="sm" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ task.title }}</div>
                                        <div class="text-[#52666b] dark:text-[#b8d5d2]">{{ task.priority }} · due {{ formatDateTime(task.due_at) }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span v-if="task.completed_at" class="rounded-full bg-[#e4f5f6] px-2 py-1 text-xs dark:bg-[#183640]">Done</span>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('task', task)">Edit</Button>
                                    </div>
                                </div>
                                <p v-if="task.description" class="mt-2 rounded-md bg-[#f6fbfb] p-2 text-[#52666b] dark:bg-[#102a32] dark:text-[#b8d5d2]">{{ task.description }}</p>
                                <p v-if="isShared && task.last_edited_by" class="mt-2 text-xs italic text-[#52666b] dark:text-[#b8d5d2]">Last edited by {{ task.last_edited_by }}</p>
                            </template>
                        </div>
                    </CardContent>
                </Card>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Task</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(taskForm, storeTask.url(trip.id), ['title', 'description', 'due_at'])">
                            <Input class="travel-touch" v-model="taskForm.title" placeholder="Task" />
                            <Input class="travel-touch" v-model="taskForm.due_at" type="datetime-local" />
                            <select v-model="taskForm.priority" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                            </select>
                            <textarea v-model="taskForm.description" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button class="travel-button-primary">Add task</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'documents'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Documents</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="document in trip.documents" :key="document.id" class="rounded-md border border-[#d8eaec] p-3 text-sm dark:border-[#25414a]">
                            <form v-if="isEditing('document', document.id)" class="grid gap-3" @submit.prevent="patchEdit(updateDocument.url({ trip: trip.id, document: document.id }))">
                                <Input class="travel-touch" v-model="editData.title" placeholder="Document title" />
                                <Input class="travel-touch" v-model="editData.document_type" placeholder="Type" />
                                <Input class="travel-touch" v-model="editData.expires_on" type="date" />
                                <textarea v-model="editData.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                <div class="flex gap-2">
                                    <Button size="sm" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ document.title }}</div>
                                        <div class="text-[#52666b] dark:text-[#b8d5d2]">{{ document.document_type }} · expires {{ formatDate(document.expires_on) }}</div>
                                    </div>
                                    <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('document', document)">Edit</Button>
                                </div>
                                <p v-if="document.notes" class="mt-2 rounded-md bg-[#f6fbfb] p-2 text-[#52666b] dark:bg-[#102a32] dark:text-[#b8d5d2]">{{ document.notes }}</p>
                                <p v-if="isShared && document.last_edited_by" class="mt-2 text-xs italic text-[#52666b] dark:text-[#b8d5d2]">Last edited by {{ document.last_edited_by }}</p>
                            </template>
                        </div>
                    </CardContent>
                </Card>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Document Note</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(documentForm, storeDocument.url(trip.id), ['title', 'expires_on', 'notes'])">
                            <Input class="travel-touch" v-model="documentForm.title" placeholder="Document title" />
                            <Input class="travel-touch" v-model="documentForm.document_type" placeholder="Type" />
                            <Input class="travel-touch" v-model="documentForm.expires_on" type="date" />
                            <textarea v-model="documentForm.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button class="travel-button-primary">Add document</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'imports'" class="grid gap-4 lg:grid-cols-[1fr_24rem]">
                <div class="space-y-4">
                    <Card class="travel-panel">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base"><Sparkles class="h-5 w-5 text-[#0f777f]" /> Automation Suggestions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="trip.automation_suggestions.length" class="space-y-2">
                                <div v-for="suggestion in trip.automation_suggestions" :key="suggestion.id" class="rounded-md border border-[#d8eaec] p-3 dark:border-[#25414a]">
                                    <div class="text-sm font-semibold">{{ suggestion.summary }}</div>
                                    <div class="mt-3 flex gap-2">
                                        <Button size="sm" class="travel-button-primary" @click="simplePost(`/trips/${trip.id}/automation/${suggestion.id}/accept`)">Accept</Button>
                                        <Button size="sm" class="travel-touch" variant="outline" @click="simplePost(`/trips/${trip.id}/automation/${suggestion.id}/dismiss`)">Dismiss</Button>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-[#52666b] dark:text-[#b8d5d2]">No active suggestions. Refresh after adding or importing details.</p>
                            <Button class="travel-touch" variant="outline" @click="simplePost(`/trips/${trip.id}/automation/refresh`)">
                                <Sparkles class="h-4 w-4" />
                                Refresh suggestions
                            </Button>
                        </CardContent>
                    </Card>

                    <Card class="travel-panel">
                        <CardHeader><CardTitle class="text-base">Import Review Queue</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="batch in trip.import_batches" :key="batch.id" class="rounded-md border border-[#d8eaec] p-3 text-sm dark:border-[#25414a]">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold">{{ batch.source_type.toUpperCase() }} import</div>
                                        <div class="text-[#52666b] dark:text-[#b8d5d2]">{{ batch.status }} · {{ (batch.parsed_payload?.items || []).length }} parsed item(s)</div>
                                    </div>
                                    <span class="rounded-full bg-[#e4f5f6] px-2 py-1 text-xs dark:bg-[#183640]">{{ batch.status }}</span>
                                </div>
                                <div v-if="batch.status === 'reviewing'" class="mt-3 flex flex-wrap gap-2">
                                    <Button size="sm" class="travel-button-primary" @click="simplePost(`/trips/${trip.id}/imports/${batch.id}/commit`)">Commit</Button>
                                    <Button size="sm" class="travel-touch" variant="outline" @click="simplePost(`/trips/${trip.id}/imports/${batch.id}/discard`)">Discard</Button>
                                </div>
                            </div>
                            <p v-if="!trip.import_batches.length" class="text-sm text-[#52666b] dark:text-[#b8d5d2]">No imports yet.</p>
                        </CardContent>
                    </Card>
                </div>

                <Card class="travel-panel">
                    <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Upload class="h-5 w-5" /> Paste Import</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(importForm, `/trips/${trip.id}/imports`, ['raw_text'])">
                            <select v-model="importForm.source_type" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="confirmation">Confirmation text</option>
                                <option value="ics">ICS calendar text</option>
                            </select>
                            <textarea v-model="importForm.raw_text" class="min-h-64 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Paste a confirmation email, booking details, or ICS file contents here." />
                            <InputError :message="importForm.errors.raw_text" />
                            <Button class="travel-button-primary">Parse for review</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'sharing'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Users class="h-5 w-5" /> Shared Travelers</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="collaborator in trip.collaborators" :key="collaborator.id" class="flex items-center justify-between rounded-md border border-[#d8eaec] p-3 text-sm dark:border-[#25414a]">
                            <div>
                                <div class="font-medium">{{ collaborator.email }}</div>
                                <div class="text-[#52666b] dark:text-[#b8d5d2]">{{ collaborator.role }} · {{ collaborator.accepted_at ? 'accepted' : 'pending' }}</div>
                            </div>
                            <Button v-if="trip.can_share" size="sm" class="travel-touch" variant="outline" @click="destroyCollaborator(collaborator.id)">Remove</Button>
                        </div>
                        <p v-if="!trip.collaborators.length" class="text-sm text-[#52666b] dark:text-[#b8d5d2]">No collaborators yet.</p>
                    </CardContent>
                </Card>
                <div class="space-y-4">
                    <Card class="travel-panel">
                        <CardHeader><CardTitle class="text-base">Invite Collaborator</CardTitle></CardHeader>
                        <CardContent>
                            <form class="grid gap-3" @submit.prevent="post(collaboratorForm, `/trips/${trip.id}/collaborators`, ['email'])">
                                <Input class="travel-touch" v-model="collaboratorForm.email" type="email" placeholder="Email address" />
                                <select v-model="collaboratorForm.role" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                    <option value="editor">Editor</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                                <Button class="travel-button-primary" :disabled="!trip.can_share">Invite</Button>
                            </form>
                        </CardContent>
                    </Card>
                    <Card class="travel-panel">
                        <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Bell class="h-5 w-5" /> Reminders</CardTitle></CardHeader>
                        <CardContent class="space-y-2">
                            <div v-for="reminder in trip.reminders" :key="reminder.id" class="rounded-md border border-[#d8eaec] p-3 text-sm dark:border-[#25414a]">
                                <form v-if="isEditing('reminder', reminder.id)" class="grid gap-3" @submit.prevent="patchEdit(updateReminder.url({ trip: trip.id, reminder: reminder.id }))">
                                    <Input class="travel-touch" v-model="editData.label" placeholder="Reminder label" />
                                    <Input class="travel-touch" v-model="editData.remind_at" type="datetime-local" />
                                    <Input class="travel-touch" v-model="editData.timezone" placeholder="Timezone" />
                                    <textarea v-model="editData.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                    <div class="flex gap-2">
                                        <Button size="sm" class="travel-button-primary">Save</Button>
                                        <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                    </div>
                                </form>
                                <template v-else>
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-medium">{{ reminder.label }}</div>
                                            <div class="text-[#52666b] dark:text-[#b8d5d2]">{{ formatDateTime(reminder.remind_at, reminder.timezone) }}</div>
                                        </div>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('reminder', reminder)">Edit</Button>
                                    </div>
                                    <p v-if="reminder.notes" class="mt-2 rounded-md bg-[#f6fbfb] p-2 text-[#52666b] dark:bg-[#102a32] dark:text-[#b8d5d2]">{{ reminder.notes }}</p>
                                    <p v-if="isShared && reminder.last_edited_by" class="mt-2 text-xs italic text-[#52666b] dark:text-[#b8d5d2]">Last edited by {{ reminder.last_edited_by }}</p>
                                </template>
                            </div>
                            <p v-if="!trip.reminders.length" class="text-sm text-[#52666b] dark:text-[#b8d5d2]">No reminders yet.</p>
                        </CardContent>
                    </Card>
                    <Card class="travel-panel">
                        <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Bell class="h-5 w-5" /> Add Reminder</CardTitle></CardHeader>
                        <CardContent>
                            <form class="grid gap-3" @submit.prevent="post(reminderForm, storeReminder.url(trip.id), ['label', 'remind_at', 'notes'])">
                                <Input class="travel-touch" v-model="reminderForm.label" placeholder="Reminder label" />
                                <Input class="travel-touch" v-model="reminderForm.remind_at" type="datetime-local" />
                                <Input class="travel-touch" v-model="reminderForm.timezone" placeholder="Timezone" />
                                <textarea v-model="reminderForm.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                <Button class="travel-button-primary">Add reminder</Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </div>
</template>
