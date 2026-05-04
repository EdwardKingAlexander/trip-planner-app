<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Bell,
    CalendarClock,
    CheckSquare,
    DollarSign,
    FileText,
    Hotel,
    ListChecks,
    Plane,
    Share2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

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
            location_name: string | null;
            starts_at: string | null;
            timezone: string;
            status: string;
        }>;
    }>;
    reservations: Array<Record<string, any>>;
    costs: Array<Record<string, any>>;
    packing_items: Array<Record<string, any>>;
    tasks: Array<Record<string, any>>;
    documents: Array<Record<string, any>>;
    reminders: Array<Record<string, any>>;
    collaborators: Array<Record<string, any>>;
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
});

const collaboratorForm = useForm({
    email: '',
    role: 'editor',
});

const panels = [
    { id: 'itinerary', label: 'Itinerary', icon: CalendarClock },
    { id: 'reservations', label: 'Reservations', icon: Plane },
    { id: 'budget', label: 'Budget', icon: DollarSign },
    { id: 'packing', label: 'Packing', icon: ListChecks },
    { id: 'tasks', label: 'Tasks', icon: CheckSquare },
    { id: 'documents', label: 'Documents', icon: FileText },
    { id: 'sharing', label: 'Sharing', icon: Share2 },
];

const plannedTotal = computed(() => props.trip.costs.reduce((sum, cost) => sum + Number(cost.planned_amount ?? 0), 0));
const actualTotal = computed(() => props.trip.costs.reduce((sum, cost) => sum + Number(cost.actual_amount ?? 0), 0));
const completedTasks = computed(() => props.trip.tasks.filter((task) => task.completed_at).length);

const post = (form: ReturnType<typeof useForm>, url: string, resetFields?: string[]) => {
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => resetFields ? form.reset(...resetFields) : form.reset(),
    });
};

const destroyCollaborator = (id: number) => {
    router.delete(`/trips/${props.trip.id}/collaborators/${id}`, { preserveScroll: true });
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

    <div class="min-h-full bg-[#f7f3ec] text-[#211f1a] dark:bg-[#11100e] dark:text-[#f3efe7]">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <header class="overflow-hidden rounded-lg border border-[#d8cdbb] bg-[#fffaf1] shadow-sm dark:border-[#3b352d] dark:bg-[#181613]">
                <div class="h-2 bg-[linear-gradient(90deg,#1b6b6f,#d98935,#b7472a,#223843)]" />
                <div class="grid gap-6 p-6 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div>
                        <Link href="/trips" class="text-sm font-medium text-[#1b6b6f]">Back to trips</Link>
                        <h1 class="mt-3 text-3xl font-semibold sm:text-5xl">{{ trip.name }}</h1>
                        <p class="mt-2 text-sm text-[#655c50] dark:text-[#c8beb0]">
                            {{ trip.destination }} · {{ formatDate(trip.starts_on) }} - {{ formatDate(trip.ends_on) }} · {{ trip.length }}
                        </p>
                        <p v-if="trip.summary" class="mt-4 max-w-3xl text-sm leading-6 text-[#655c50] dark:text-[#c8beb0]">{{ trip.summary }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 rounded-lg border border-[#d8cdbb] bg-white/70 p-3 text-center dark:border-[#3b352d] dark:bg-black/20">
                        <div>
                            <div class="text-2xl font-semibold">{{ trip.reservations.length }}</div>
                            <div class="text-xs text-[#776d60]">Bookings</div>
                        </div>
                        <div>
                            <div class="text-2xl font-semibold">{{ trip.days.length }}</div>
                            <div class="text-xs text-[#776d60]">Days</div>
                        </div>
                        <div>
                            <div class="text-2xl font-semibold">{{ trip.collaborators.length }}</div>
                            <div class="text-xs text-[#776d60]">Shared</div>
                        </div>
                    </div>
                </div>
            </header>

            <nav class="flex gap-2 overflow-x-auto rounded-lg border border-[#d8cdbb] bg-white p-2 dark:border-[#3b352d] dark:bg-[#181613]">
                <button
                    v-for="panel in panels"
                    :key="panel.id"
                    type="button"
                    class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium"
                    :class="activePanel === panel.id ? 'bg-[#1b6b6f] text-white' : 'text-[#655c50] hover:bg-[#f0e7d9] dark:text-[#c8beb0] dark:hover:bg-[#24201b]'"
                    @click="activePanel = panel.id"
                >
                    <component :is="panel.icon" class="h-4 w-4" />
                    {{ panel.label }}
                </button>
            </nav>

            <section v-if="activePanel === 'itinerary'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <div class="space-y-4">
                    <Card v-for="day in trip.days" :key="day.id" class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                        <CardHeader>
                            <CardTitle class="text-base">{{ day.title }} · {{ formatDate(day.date) }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="day.items.length" class="space-y-2">
                                <div v-for="item in day.items" :key="item.id" class="rounded-md border border-[#e2d7c6] p-3 dark:border-[#3b352d]">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold">{{ item.title }}</div>
                                            <div class="text-xs text-[#655c50]">{{ item.type }} · {{ formatDateTime(item.starts_at, item.timezone) }}</div>
                                        </div>
                                        <span class="rounded-full bg-[#f0e7d9] px-2 py-1 text-xs dark:bg-[#24201b]">{{ item.status }}</span>
                                    </div>
                                    <p v-if="item.location_name" class="mt-2 text-sm text-[#655c50]">{{ item.location_name }}</p>
                                </div>
                            </div>
                            <p v-else class="text-sm text-[#655c50]">No items planned for this day yet.</p>
                        </CardContent>
                    </Card>
                </div>
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Add Itinerary Item</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(itineraryForm, `/trips/${trip.id}/itinerary-items`, ['title', 'description', 'location_name', 'starts_at', 'ends_at'])">
                            <select v-model="itineraryForm.trip_day_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option :value="null">Unscheduled idea</option>
                                <option v-for="day in trip.days" :key="day.id" :value="day.id">{{ day.title }} · {{ day.date }}</option>
                            </select>
                            <Input v-model="itineraryForm.title" placeholder="Title" />
                            <InputError :message="itineraryForm.errors.title" />
                            <Input v-model="itineraryForm.location_name" placeholder="Location" />
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="itineraryForm.starts_at" type="datetime-local" />
                                <Input v-model="itineraryForm.ends_at" type="datetime-local" />
                            </div>
                            <Input v-model="itineraryForm.timezone" placeholder="Timezone" />
                            <select v-model="itineraryForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="activity">Activity</option>
                                <option value="dining">Dining</option>
                                <option value="transport">Transport</option>
                                <option value="note">Note</option>
                                <option value="custom">Custom</option>
                            </select>
                            <Button class="bg-[#1b6b6f] hover:bg-[#155356]">Add item</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'reservations'" class="grid gap-4 lg:grid-cols-[1fr_24rem]">
                <div class="grid gap-3">
                    <Card v-for="reservation in trip.reservations" :key="reservation.id" class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                        <CardContent class="pt-6">
                            <div class="flex items-start gap-3">
                                <Plane v-if="reservation.type === 'flight'" class="mt-1 h-5 w-5 text-[#1b6b6f]" />
                                <Hotel v-else class="mt-1 h-5 w-5 text-[#8a5b26]" />
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold">{{ reservation.title }}</div>
                                    <div class="text-sm text-[#655c50]">{{ reservation.provider_name || reservation.type }} · {{ reservation.booking_reference || 'No confirmation yet' }}</div>
                                    <div class="mt-2 text-sm">{{ formatDateTime(reservation.starts_at, reservation.starts_timezone) }} - {{ formatDateTime(reservation.ends_at, reservation.ends_timezone) }}</div>
                                    <p v-if="reservation.address" class="mt-2 text-sm text-[#655c50]">{{ reservation.address }}</p>
                                </div>
                                <span class="rounded-full bg-[#f0e7d9] px-2 py-1 text-xs dark:bg-[#24201b]">{{ reservation.status }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Add Reservation</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(reservationForm, `/trips/${trip.id}/reservations`, ['title', 'provider_name', 'booking_reference', 'starts_at', 'ends_at', 'location_name', 'address', 'notes', 'airline', 'flight_number', 'departure_airport', 'arrival_airport', 'property_name', 'room_type'])">
                            <select v-model="reservationForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="flight">Flight</option>
                                <option value="lodging">Hotel / lodging</option>
                                <option value="transport">Ground transport</option>
                                <option value="activity">Activity</option>
                                <option value="dining">Dining</option>
                                <option value="custom">Custom</option>
                            </select>
                            <Input v-model="reservationForm.title" placeholder="Reservation title" />
                            <InputError :message="reservationForm.errors.title" />
                            <Input v-model="reservationForm.provider_name" placeholder="Provider" />
                            <Input v-model="reservationForm.booking_reference" placeholder="Confirmation number" />
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="reservationForm.starts_at" type="datetime-local" />
                                <Input v-model="reservationForm.ends_at" type="datetime-local" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="reservationForm.starts_timezone" placeholder="Start timezone" />
                                <Input v-model="reservationForm.ends_timezone" placeholder="End timezone" />
                            </div>
                            <template v-if="reservationForm.type === 'flight'">
                                <div class="grid grid-cols-2 gap-3">
                                    <Input v-model="reservationForm.airline" placeholder="Airline" />
                                    <Input v-model="reservationForm.flight_number" placeholder="Flight #" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <Input v-model="reservationForm.departure_airport" placeholder="From airport" />
                                    <Input v-model="reservationForm.arrival_airport" placeholder="To airport" />
                                </div>
                            </template>
                            <template v-if="reservationForm.type === 'lodging'">
                                <Input v-model="reservationForm.property_name" placeholder="Property name" />
                                <Input v-model="reservationForm.room_type" placeholder="Room type" />
                            </template>
                            <Input v-model="reservationForm.address" placeholder="Address" />
                            <Button class="bg-[#1b6b6f] hover:bg-[#155356]">Add reservation</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'budget'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Budget</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-md bg-[#f0e7d9] p-4 dark:bg-[#24201b]">
                                <div class="text-xs uppercase text-[#655c50]">Planned</div>
                                <div class="text-2xl font-semibold">${{ plannedTotal.toFixed(2) }}</div>
                            </div>
                            <div class="rounded-md bg-[#f0e7d9] p-4 dark:bg-[#24201b]">
                                <div class="text-xs uppercase text-[#655c50]">Actual</div>
                                <div class="text-2xl font-semibold">${{ actualTotal.toFixed(2) }}</div>
                            </div>
                        </div>
                        <div v-for="cost in trip.costs" :key="cost.id" class="flex justify-between rounded-md border border-[#e2d7c6] p-3 text-sm dark:border-[#3b352d]">
                            <span>{{ cost.label }} · {{ cost.category }}</span>
                            <span>{{ cost.currency }} {{ cost.actual_amount || cost.planned_amount || '0.00' }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Add Cost</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(costForm, `/trips/${trip.id}/costs`, ['label', 'planned_amount', 'actual_amount', 'notes'])">
                            <Input v-model="costForm.label" placeholder="Label" />
                            <select v-model="costForm.category" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="flight">Flight</option>
                                <option value="lodging">Lodging</option>
                                <option value="food">Food</option>
                                <option value="activity">Activity</option>
                                <option value="transport">Transport</option>
                                <option value="custom">Custom</option>
                            </select>
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="costForm.planned_amount" type="number" min="0" step="0.01" placeholder="Planned" />
                                <Input v-model="costForm.actual_amount" type="number" min="0" step="0.01" placeholder="Actual" />
                            </div>
                            <Input v-model="costForm.currency" maxlength="3" placeholder="USD" />
                            <Button class="bg-[#1b6b6f] hover:bg-[#155356]">Add cost</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'packing'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Packing List</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="item in trip.packing_items" :key="item.id" class="flex items-center justify-between rounded-md border border-[#e2d7c6] p-3 text-sm dark:border-[#3b352d]">
                            <span>{{ item.quantity }}x {{ item.label }} <span class="text-[#655c50]">· {{ item.category }}</span></span>
                            <span>{{ item.traveler_name || 'Shared' }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Add Packing Item</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(packingForm, `/trips/${trip.id}/packing-items`, ['traveler_name', 'label', 'notes'])">
                            <Input v-model="packingForm.label" placeholder="Item" />
                            <Input v-model="packingForm.traveler_name" placeholder="Traveler name" />
                            <div class="grid grid-cols-2 gap-3">
                                <Input v-model="packingForm.category" placeholder="Category" />
                                <Input v-model="packingForm.quantity" type="number" min="1" />
                            </div>
                            <Button class="bg-[#1b6b6f] hover:bg-[#155356]">Add item</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'tasks'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Tasks · {{ completedTasks }}/{{ trip.tasks.length }} done</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="task in trip.tasks" :key="task.id" class="rounded-md border border-[#e2d7c6] p-3 text-sm dark:border-[#3b352d]">
                            <div class="font-medium">{{ task.title }}</div>
                            <div class="text-[#655c50]">{{ task.priority }} · due {{ formatDateTime(task.due_at) }}</div>
                        </div>
                    </CardContent>
                </Card>
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Add Task</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(taskForm, `/trips/${trip.id}/tasks`, ['title', 'description', 'due_at'])">
                            <Input v-model="taskForm.title" placeholder="Task" />
                            <Input v-model="taskForm.due_at" type="datetime-local" />
                            <select v-model="taskForm.priority" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                            </select>
                            <Button class="bg-[#1b6b6f] hover:bg-[#155356]">Add task</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'documents'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Documents</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="document in trip.documents" :key="document.id" class="rounded-md border border-[#e2d7c6] p-3 text-sm dark:border-[#3b352d]">
                            <div class="font-medium">{{ document.title }}</div>
                            <div class="text-[#655c50]">{{ document.document_type }} · expires {{ formatDate(document.expires_on) }}</div>
                        </div>
                    </CardContent>
                </Card>
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="text-base">Add Document Note</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(documentForm, `/trips/${trip.id}/documents`, ['title', 'expires_on', 'notes'])">
                            <Input v-model="documentForm.title" placeholder="Document title" />
                            <Input v-model="documentForm.document_type" placeholder="Type" />
                            <Input v-model="documentForm.expires_on" type="date" />
                            <Button class="bg-[#1b6b6f] hover:bg-[#155356]">Add document</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'sharing'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                    <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Users class="h-5 w-5" /> Shared Travelers</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="collaborator in trip.collaborators" :key="collaborator.id" class="flex items-center justify-between rounded-md border border-[#e2d7c6] p-3 text-sm dark:border-[#3b352d]">
                            <div>
                                <div class="font-medium">{{ collaborator.email }}</div>
                                <div class="text-[#655c50]">{{ collaborator.role }} · {{ collaborator.accepted_at ? 'accepted' : 'pending' }}</div>
                            </div>
                            <Button v-if="trip.can_share" size="sm" variant="outline" @click="destroyCollaborator(collaborator.id)">Remove</Button>
                        </div>
                        <p v-if="!trip.collaborators.length" class="text-sm text-[#655c50]">No collaborators yet.</p>
                    </CardContent>
                </Card>
                <div class="space-y-4">
                    <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                        <CardHeader><CardTitle class="text-base">Invite Collaborator</CardTitle></CardHeader>
                        <CardContent>
                            <form class="grid gap-3" @submit.prevent="post(collaboratorForm, `/trips/${trip.id}/collaborators`, ['email'])">
                                <Input v-model="collaboratorForm.email" type="email" placeholder="Email address" />
                                <select v-model="collaboratorForm.role" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                    <option value="editor">Editor</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                                <Button class="bg-[#1b6b6f] hover:bg-[#155356]" :disabled="!trip.can_share">Invite</Button>
                            </form>
                        </CardContent>
                    </Card>
                    <Card class="rounded-lg border-[#d8cdbb] dark:border-[#3b352d]">
                        <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Bell class="h-5 w-5" /> Reminder</CardTitle></CardHeader>
                        <CardContent>
                            <form class="grid gap-3" @submit.prevent="post(reminderForm, `/trips/${trip.id}/reminders`, ['label', 'remind_at'])">
                                <Input v-model="reminderForm.label" placeholder="Reminder label" />
                                <Input v-model="reminderForm.remind_at" type="datetime-local" />
                                <Input v-model="reminderForm.timezone" placeholder="Timezone" />
                                <Button class="bg-[#1b6b6f] hover:bg-[#155356]">Add reminder</Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </div>
</template>
