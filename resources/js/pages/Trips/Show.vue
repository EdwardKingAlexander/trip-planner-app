<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarClock,
    CheckCircle2,
    CheckSquare,
    ChevronDown,
    Download,
    DollarSign,
    FileQuestion,
    FileText,
    Hotel,
    Image,
    ListChecks,
    Paperclip,
    Plane,
    Printer,
    Share2,
    Sparkles,
    StickyNote,
    Trash2,
    Upload,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { nextTick, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTripRealtime } from '@/composables/useTripRealtime';
import { destroy as destroyCost, store as storeCost, update as updateCost } from '@/routes/trips/costs';
import { destroy as destroyDocument, store as storeDocument, update as updateDocument, upload as uploadDocument } from '@/routes/trips/documents';
import { destroy as destroyItineraryItem, store as storeItineraryItem, update as updateItineraryItem } from '@/routes/trips/itinerary-items';
import { destroy as destroyPackingItem, store as storePackingItem, togglePacked, update as updatePackingItem } from '@/routes/trips/packing-items';
import { destroy as destroyReminder, store as storeReminder, update as updateReminder } from '@/routes/trips/reminders';
import { destroy as destroyReservation, store as storeReservation, update as updateReservation } from '@/routes/trips/reservations';
import { destroy as destroyTask, store as storeTask, toggleCompletion, update as updateTask } from '@/routes/trips/tasks';

type ParticipantSummary = {
    id: number;
    name: string;
    first_name: string;
    initials: string;
};

type PackingItem = Record<string, any> & {
    id: number;
    is_packed: boolean;
    sort_order?: number | null;
    label: string;
    quantity: number;
    traveler_name?: string | null;
    category: string;
    notes?: string | null;
    assigned_to_user_id: number | null;
    added_by: ParticipantSummary | null;
    assigned_to: ParticipantSummary | null;
};

type TaskItem = Record<string, any> & {
    id: number;
    title: string;
    description?: string | null;
    due_at: string | null;
    completed_at: string | null;
    priority: string;
    last_edited_by?: string | null;
};

type TripDocument = Record<string, any> & {
    id: number;
    title: string;
    document_type: string;
    expires_on: string | null;
    notes: string | null;
    reservation_id: number | null;
    file_path: string | null;
    original_filename: string | null;
    mime_type: string | null;
    file_size_bytes: number | null;
    file_url: string | null;
    preview_kind: 'note' | 'pdf' | 'image' | 'image-opaque' | 'file';
    size_label: string | null;
    last_edited_by?: string | null;
};

type ReservationItem = Record<string, any> & {
    id: number;
    title: string;
    type: string;
    document_count: number;
};

type AssigneeProgress = {
    key: string;
    label: string;
    initials: string;
    packed: number;
    total: number;
};

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
    activity_version?: number;
    last_event?: {
        id: number;
        changed_area: string | null;
        event_type: string | null;
        summary: string | null;
        actor_first_name: string | null;
        actor_user_id: number | null;
        created_at: string | null;
    } | null;
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
            last_edited_by?: string | null;
        }>;
        tasks: TaskItem[];
    }>;
    reservations: ReservationItem[];
    costs: Array<Record<string, any>>;
    packing_items: PackingItem[];
    tasks: TaskItem[];
    documents: TripDocument[];
    reminders: Array<Record<string, any>>;
    collaborators: Array<Record<string, any>>;
    participants: Array<ParticipantSummary & { role: 'owner' | 'editor' | 'viewer' }>;
    import_batches: Array<Record<string, any>>;
    automation_suggestions: Array<Record<string, any>>;
};

const props = defineProps<{ trip: Trip }>();
const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trips', href: '/trips' },
            { title: 'Trip', href: '#' },
        ],
    },
});

useTripRealtime();

const activePanel = ref('itinerary');
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
type EditKind = 'itinerary' | 'reservation' | 'cost' | 'packing' | 'task' | 'document' | 'reminder';
const editing = ref<{ type: EditKind; id: number } | null>(null);
const editData = ref<Record<string, any>>({});
const togglingPackingIds = ref(new Set<number>());
const togglingTaskIds = ref(new Set<number>());
const packingRowErrors = ref<Record<number, string>>({});
const taskRowErrors = ref<Record<number, string>>({});
const hidePacked = ref(false);
const mineOnly = ref(false);
const missingSubjectAlert = ref(false);
const dropActive = ref(false);
const fileInputEl = ref<HTMLInputElement | null>(null);
const stagedFileErrors = ref<string[]>([]);
const lightboxDocument = ref<TripDocument | null>(null);
const expandedReservations = ref(new Set<number>());
const currentUserId = computed(() => page.props.auth.user?.id ?? null);

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
    assigned_to_user_id: currentUserId.value,
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
    reservation_id: null as number | null,
});

const uploadForm = useForm({
    files: [] as File[],
    title_prefix: '',
    document_type: 'attachment',
    expires_on: '',
    notes: '',
    reservation_id: null as number | null,
});

const attachmentForm = useForm({
    files: [] as File[],
    title_prefix: '',
    document_type: 'attachment',
    expires_on: '',
    notes: '',
    reservation_id: null as number | null,
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
    { id: 'reminders', label: 'Reminders', icon: Bell },
    { id: 'imports', label: 'Imports', icon: Upload },
    { id: 'sharing', label: 'Sharing', icon: Share2 },
];

function travelerInitials(value?: string | null): string {
    const cleaned = value?.trim();

    if (!cleaned) {
        return 'NA';
    }

    return cleaned
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function participantOptionLabel(participant: ParticipantSummary): string {
    return `${participant.first_name}${participant.id === currentUserId.value ? ' (me)' : ''}`;
}

function assigneeLabel(item: PackingItem): string {
    return item.assigned_to?.name ?? item.traveler_name?.trim() ?? 'Anyone';
}

function assigneeInitials(item: PackingItem): string {
    return item.assigned_to?.initials ?? travelerInitials(item.traveler_name);
}

function packingEmptyMessage(): string {
    if (mineOnly.value) {
        const hasMine = props.trip.packing_items.some((item) => item.assigned_to_user_id === currentUserId.value);

        if (!hasMine) {
            return 'Nothing assigned to you yet. Assign a packing item to your name to claim it.';
        }

        return hidePacked.value ? "You're all packed." : 'No packing items match this filter.';
    }

    if (hidePacked.value) {
        return "Everything's packed. Bon voyage.";
    }

    return 'No packing items yet.';
}

const hidePackedKey = computed(() => `packing-hide-packed:${props.trip.id}`);
const mineOnlyKey = computed(() => `packing-mine-only:${props.trip.id}`);
const packedCount = computed(() => props.trip.packing_items.filter((item) => item.is_packed).length);
const totalPackingCount = computed(() => props.trip.packing_items.length);
const packedPercentage = computed(() => totalPackingCount.value === 0 ? 0 : Math.round((packedCount.value / totalPackingCount.value) * 100));
const visiblePackingItems = computed(() => {
    let items = props.trip.packing_items;

    if (mineOnly.value && currentUserId.value !== null) {
        items = items.filter((item) => item.assigned_to_user_id === currentUserId.value);
    }

    if (hidePacked.value) {
        items = items.filter((item) => !item.is_packed);
    }

    return items;
});
const orderedPackingItems = computed(() => [...visiblePackingItems.value].sort((a, b) => {
    if (a.is_packed === b.is_packed) {
        return (a.sort_order ?? 0) - (b.sort_order ?? 0);
    }

    return a.is_packed ? 1 : -1;
}));
const assigneeProgress = computed<AssigneeProgress[]>(() => {
    const buckets = new Map<string, AssigneeProgress>();

    for (const item of props.trip.packing_items) {
        const travelerName = item.traveler_name?.trim();
        const key = item.assigned_to_user_id !== null
            ? `user-${item.assigned_to_user_id}`
            : travelerName
                ? `name-${travelerName.toLowerCase()}`
                : 'unassigned';

        if (!buckets.has(key)) {
            buckets.set(key, {
                key,
                label: item.assigned_to?.first_name ?? travelerName ?? 'Unassigned',
                initials: item.assigned_to?.initials ?? travelerInitials(travelerName),
                packed: 0,
                total: 0,
            });
        }

        const bucket = buckets.get(key);

        if (bucket) {
            bucket.total += 1;

            if (item.is_packed) {
                bucket.packed += 1;
            }
        }
    }

    const userOrder = new Map(props.trip.participants.map((participant, index) => [`user-${participant.id}`, index]));

    return [...buckets.entries()]
        .sort(([keyA], [keyB]) => {
            const userA = userOrder.get(keyA);
            const userB = userOrder.get(keyB);

            if (userA !== undefined && userB !== undefined) {
                return userA - userB;
            }

            if (userA !== undefined) {
                return -1;
            }

            if (userB !== undefined) {
                return 1;
            }

            if (keyA === 'unassigned') {
                return 1;
            }

            if (keyB === 'unassigned') {
                return -1;
            }

            return keyA.localeCompare(keyB);
        })
        .map(([, value]) => value);
});

onMounted(() => {
    hidePacked.value = window.localStorage.getItem(hidePackedKey.value) === '1';
    mineOnly.value = window.localStorage.getItem(mineOnlyKey.value) === '1';

    const url = new URL(window.location.href);
    const focusPanel = url.searchParams.get('focus');
    const hashPanel = window.location.hash.replace('#', '');

    if (focusPanel && panels.some((panel) => panel.id === focusPanel)) {
        activePanel.value = focusPanel;
    } else if (panels.some((panel) => panel.id === hashPanel)) {
        activePanel.value = hashPanel;
    }

    if (url.searchParams.get('missing') === '1') {
        missingSubjectAlert.value = true;
        window.setTimeout(() => {
            missingSubjectAlert.value = false;
        }, 5000);
    }

    if (url.searchParams.get('from') === 'notification' && window.location.hash) {
        void focusDeepLinkedElement(window.location.hash.slice(1));
    }

    for (const reservation of props.trip.reservations) {
        if (reservation.document_count > 0) {
            expandedReservations.value.add(reservation.id);
        }
    }
});

watch(hidePacked, (value) => {
    window.localStorage.setItem(hidePackedKey.value, value ? '1' : '0');
});

watch(mineOnly, (value) => {
    window.localStorage.setItem(mineOnlyKey.value, value ? '1' : '0');
});

const plannedTotal = computed(() => props.trip.costs.reduce((sum, cost) => sum + Number(cost.planned_amount ?? 0), 0));
const actualTotal = computed(() => props.trip.costs.reduce((sum, cost) => sum + Number(cost.actual_amount ?? 0), 0));
const completedTasks = computed(() => props.trip.tasks.filter((task) => task.completed_at).length);
const isShared = computed(() => (props.trip.collaborators?.length ?? 0) > 0);
const documentsByReservation = computed(() => {
    const map = new Map<number, TripDocument[]>();

    for (const document of props.trip.documents) {
        if (document.reservation_id === null) {
            continue;
        }

        if (!map.has(document.reservation_id)) {
            map.set(document.reservation_id, []);
        }

        map.get(document.reservation_id)?.push(document);
    }

    return map;
});

const focusDeepLinkedElement = async (anchor: string) => {
    await nextTick();
    window.setTimeout(() => {
        const element = document.getElementById(anchor);

        if (!element) {
            return;
        }

        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus({ preventScroll: true });
        element.setAttribute('data-focus-pulse', 'true');

        window.setTimeout(() => {
            element.removeAttribute('data-focus-pulse');
        }, 1200);
    }, 0);
};

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

const destroyEntry = (url: string, label: string) => {
    if (!window.confirm(`Delete ${label}?`)) {
        return;
    }

    router.delete(url, {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
};

const allowedDocumentExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
const maxDocumentFileSize = 15 * 1024 * 1024;

const formatBytes = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const acceptedFiles = (files: FileList | File[]): File[] => {
    const nextErrors: string[] = [];
    const accepted = Array.from(files).filter((file) => {
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!allowedDocumentExtensions.includes(extension)) {
            nextErrors.push(`${file.name} is not a supported file type.`);

            return false;
        }

        if (file.size > maxDocumentFileSize) {
            nextErrors.push(`${file.name} is larger than 15 MB.`);

            return false;
        }

        return true;
    });

    stagedFileErrors.value = nextErrors;

    return accepted.slice(0, 10);
};

const stageUploadFiles = (files: FileList | File[]) => {
    const accepted = acceptedFiles(files);
    const remainingSlots = Math.max(0, 10 - uploadForm.files.length);

    if (accepted.length > remainingSlots) {
        stagedFileErrors.value.push('Only 10 files can be uploaded at once.');
    }

    uploadForm.files = [...uploadForm.files, ...accepted.slice(0, remainingSlots)];
};

const handleFileInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    stageUploadFiles(input.files ?? []);
    input.value = '';
};

const handleDrop = (event: DragEvent) => {
    dropActive.value = false;
    stageUploadFiles(event.dataTransfer?.files ?? []);
};

const removeStagedFile = (index: number) => {
    uploadForm.files = uploadForm.files.filter((_, currentIndex) => currentIndex !== index);
};

const submitUpload = () => {
    if (uploadForm.files.length === 0) {
        return;
    }

    uploadForm.post(uploadDocument.url(props.trip.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset('files', 'title_prefix', 'document_type', 'expires_on', 'notes', 'reservation_id');
            stagedFileErrors.value = [];
        },
    });
};

const stageReservationAttachment = (reservationId: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    const accepted = acceptedFiles(input.files ?? []);
    attachmentForm.files = accepted;
    attachmentForm.reservation_id = reservationId;
    input.value = '';
};

const submitReservationAttachment = () => {
    if (attachmentForm.files.length === 0 || attachmentForm.reservation_id === null) {
        return;
    }

    attachmentForm.post(uploadDocument.url(props.trip.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            attachmentForm.reset('files', 'title_prefix', 'document_type', 'expires_on', 'notes', 'reservation_id');
        },
    });
};

const documentFileUrl = (document: TripDocument, download = false): string | null => {
    if (document.file_url === null) {
        return null;
    }

    return download ? `${document.file_url}?download=1` : document.file_url;
};

const openLightbox = (document: TripDocument) => {
    lightboxDocument.value = document;
};

const closeLightbox = () => {
    lightboxDocument.value = null;
};

const reservationTitleFor = (id: number | null): string => {
    if (id === null) {
        return 'Not linked';
    }

    return props.trip.reservations.find((reservation) => reservation.id === id)?.title ?? 'Reservation';
};

const toggleReservationAttachments = (id: number) => {
    const next = new Set(expandedReservations.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expandedReservations.value = next;
};

const isReservationExpanded = (id: number): boolean => expandedReservations.value.has(id);

const setTaskCompletion = (event: Event) => {
    editData.value.completed_at = (event.target as HTMLInputElement).checked ? new Date().toISOString().slice(0, 16) : null;
};

const setPackingRowError = (id: number, message: string) => {
    packingRowErrors.value = { ...packingRowErrors.value, [id]: message };

    window.setTimeout(() => {
        const nextErrors = { ...packingRowErrors.value };
        delete nextErrors[id];
        packingRowErrors.value = nextErrors;
    }, 4000);
};

const setTaskRowError = (id: number, message: string) => {
    taskRowErrors.value = { ...taskRowErrors.value, [id]: message };

    window.setTimeout(() => {
        const nextErrors = { ...taskRowErrors.value };
        delete nextErrors[id];
        taskRowErrors.value = nextErrors;
    }, 4000);
};

const setTaskCompletionEverywhere = (taskId: number, completedAt: string | null) => {
    for (const task of props.trip.tasks) {
        if (task.id === taskId) {
            task.completed_at = completedAt;
        }
    }

    for (const day of props.trip.days) {
        for (const task of day.tasks) {
            if (task.id === taskId) {
                task.completed_at = completedAt;
            }
        }
    }
};

const toggleTaskCompletion = (task: TaskItem) => {
    if (!props.trip.can_edit || togglingTaskIds.value.has(task.id)) {
        return;
    }

    const previous = task.completed_at ?? null;
    const nextCompletedAt = previous ? null : new Date().toISOString();

    setTaskCompletionEverywhere(task.id, nextCompletedAt);
    togglingTaskIds.value = new Set(togglingTaskIds.value).add(task.id);

    router.patch(
        toggleCompletion.url({ trip: props.trip.id, task: task.id }),
        { completed: Boolean(nextCompletedAt) },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                setTaskCompletionEverywhere(task.id, previous);
                setTaskRowError(task.id, 'Could not save. Try again.');
            },
            onFinish: () => {
                const nextIds = new Set(togglingTaskIds.value);
                nextIds.delete(task.id);
                togglingTaskIds.value = nextIds;
            },
        },
    );
};

const togglePackedItem = (item: Record<string, any> & { id: number; is_packed: boolean; label: string }) => {
    if (!props.trip.can_edit || togglingPackingIds.value.has(item.id)) {
        return;
    }

    const previous = item.is_packed;
    item.is_packed = !previous;
    togglingPackingIds.value = new Set(togglingPackingIds.value).add(item.id);

    router.patch(
        togglePacked.url({ trip: props.trip.id, packingItem: item.id }),
        { is_packed: item.is_packed },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                item.is_packed = previous;
                setPackingRowError(item.id, 'Could not save. Try again.');
            },
            onFinish: () => {
                const nextIds = new Set(togglingPackingIds.value);
                nextIds.delete(item.id);
                togglingPackingIds.value = nextIds;
            },
        },
    );
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
                        <Link href="/trips" class="inline-flex min-h-11 items-center text-sm font-medium text-primary">Back to trips</Link>
                        <h1 class="mt-3 text-3xl font-semibold sm:text-5xl">{{ trip.name }}</h1>
                        <p class="travel-muted mt-2 text-sm">
                            {{ trip.destination }} · {{ formatDate(trip.starts_on) }} - {{ formatDate(trip.ends_on) }} · {{ trip.length }}
                        </p>
                        <p v-if="trip.summary" class="travel-muted mt-4 max-w-3xl text-sm leading-6">{{ trip.summary }}</p>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2 rounded-lg border border-border bg-card/85 p-3 text-center shadow-xs dark:border-border dark:bg-card/60">
                            <div class="rounded-md bg-muted p-2 dark:bg-muted">
                                <div class="text-2xl font-semibold">{{ trip.reservations.length }}</div>
                                <div class="text-xs text-muted-foreground dark:text-muted-foreground">Bookings</div>
                            </div>
                            <div class="rounded-md bg-muted p-2 dark:bg-muted">
                                <div class="text-2xl font-semibold">{{ trip.days.length }}</div>
                                <div class="text-xs text-muted-foreground dark:text-muted-foreground">Days</div>
                            </div>
                            <div class="rounded-md bg-muted p-2 dark:bg-muted">
                                <div class="text-2xl font-semibold">{{ trip.collaborators.length }}</div>
                                <div class="text-xs text-muted-foreground dark:text-muted-foreground">Shared</div>
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
                    :class="activePanel === panel.id ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent dark:text-muted-foreground dark:hover:bg-accent'"
                    @click="activePanel = panel.id"
                >
                    <component :is="panel.icon" class="h-4 w-4" />
                    {{ panel.label }}
                </button>
            </nav>

            <Alert v-if="missingSubjectAlert">
                <AlertDescription>That item is no longer available.</AlertDescription>
            </Alert>

            <section v-if="activePanel === 'itinerary'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <div class="space-y-4">
                    <Card v-for="day in trip.days" :key="day.id" class="travel-panel">
                        <CardHeader>
                            <CardTitle class="text-base">{{ day.title }} · {{ formatDate(day.date) }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="day.items.length || day.tasks.length" class="space-y-2">
                                <div v-for="item in day.items" :id="`itinerary-item-${item.id}`" :key="item.id" tabindex="-1" class="rounded-md border border-border p-3 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border">
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
                                                <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                                <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                            </div>
                                        </form>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold">{{ item.title }}</div>
                                                <div class="text-xs text-muted-foreground dark:text-muted-foreground">{{ item.type }} · {{ formatDateTime(item.starts_at, item.timezone) }}</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-accent px-2 py-1 text-xs dark:bg-accent">{{ item.status }}</span>
                                                <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('itinerary', { ...item, trip_day_id: day.id })">Edit</Button>
                                                <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyItineraryItem.url({ trip: trip.id, itineraryItem: item.id }), item.title)">
                                                    <Trash2 class="h-4 w-4" />
                                                    Delete
                                                </Button>
                                            </div>
                                        </div>
                                        <p v-if="item.location_name" class="mt-2 text-sm text-muted-foreground dark:text-muted-foreground">{{ item.location_name }}</p>
                                        <p v-if="item.description" class="mt-2 rounded-md bg-muted p-2 text-sm text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ item.description }}</p>
                                        <p v-if="isShared && item.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ item.last_edited_by }}</p>
                                    </template>
                                </div>
                                <div v-for="task in day.tasks" :key="`itinerary-task-${task.id}`" class="rounded-md border border-border bg-accent/35 p-3 dark:border-border dark:bg-accent/25">
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
                                            <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                            <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                        </div>
                                    </form>
                                    <template v-else>
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex min-w-0 items-start gap-3">
                                                <Checkbox
                                                    :model-value="Boolean(task.completed_at)"
                                                    :disabled="!trip.can_edit || togglingTaskIds.has(task.id)"
                                                    class="mt-0.5 size-5"
                                                    :aria-label="`${task.completed_at ? 'Reopen' : 'Complete'} ${task.title}`"
                                                    @update:model-value="toggleTaskCompletion(task)"
                                                />
                                                <div class="min-w-0">
                                                    <div class="text-sm font-semibold" :class="{ 'text-muted-foreground line-through': task.completed_at }">{{ task.title }}</div>
                                                    <div class="text-xs text-muted-foreground dark:text-muted-foreground">Task · {{ task.priority }} · due {{ formatDateTime(task.due_at) }}</div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span v-if="task.completed_at" class="rounded-full bg-accent px-2 py-1 text-xs dark:bg-accent">Done</span>
                                                <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('task', task)">Edit</Button>
                                                <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyTask.url({ trip: trip.id, task: task.id }), task.title)">
                                                    <Trash2 class="h-4 w-4" />
                                                    Delete
                                                </Button>
                                            </div>
                                        </div>
                                        <p v-if="taskRowErrors[task.id]" class="mt-2 rounded-md border border-destructive/30 bg-destructive/10 p-2 text-sm text-destructive">{{ taskRowErrors[task.id] }}</p>
                                        <p v-if="task.description" class="mt-2 rounded-md bg-muted p-2 text-sm text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ task.description }}</p>
                                        <p v-if="isShared && task.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ task.last_edited_by }}</p>
                                    </template>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground dark:text-muted-foreground">No items planned for this day yet.</p>
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
                            <Button type="submit" class="travel-button-primary" :disabled="itineraryForm.processing">Add item</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'reservations'" class="grid gap-4 lg:grid-cols-[1fr_24rem]">
                <div class="grid gap-3">
                    <Card v-for="reservation in trip.reservations" :id="`reservation-${reservation.id}`" :key="reservation.id" tabindex="-1" class="travel-panel outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
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
                                        <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                        <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                    </div>
                                </form>
                            </template>
                            <template v-else>
                                <div class="flex items-start gap-3">
                                    <Plane v-if="reservation.type === 'flight'" class="mt-1 h-5 w-5 text-primary" />
                                    <Hotel v-else class="mt-1 h-5 w-5 text-primary" />
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold">{{ reservation.title }}</div>
                                        <div class="text-sm text-muted-foreground dark:text-muted-foreground">{{ reservation.provider_name || reservation.type }} · {{ reservation.booking_reference || 'No confirmation yet' }}</div>
                                        <div class="mt-2 text-sm">{{ formatDateTime(reservation.starts_at, reservation.starts_timezone) }} - {{ formatDateTime(reservation.ends_at, reservation.ends_timezone) }}</div>
                                        <p v-if="reservation.address" class="mt-2 text-sm text-muted-foreground dark:text-muted-foreground">{{ reservation.address }}</p>
                                        <p v-if="reservation.notes" class="mt-2 rounded-md bg-muted p-2 text-sm text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ reservation.notes }}</p>
                                        <div class="mt-3 rounded-md border border-dashed border-border p-3">
                                            <button type="button" class="flex w-full items-center justify-between text-sm font-medium" @click="toggleReservationAttachments(reservation.id)">
                                                <span class="flex items-center gap-2">
                                                    <Paperclip class="h-4 w-4" />
                                                    Attachments
                                                    <span class="text-xs text-muted-foreground">({{ reservation.document_count }})</span>
                                                </span>
                                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': isReservationExpanded(reservation.id) }" />
                                            </button>
                                            <div v-if="isReservationExpanded(reservation.id)" class="mt-3 space-y-2">
                                                <div v-for="document in documentsByReservation.get(reservation.id) ?? []" :key="document.id" class="flex items-center gap-3 rounded-md bg-muted/50 p-2 text-sm">
                                                    <button v-if="document.preview_kind === 'image'" type="button" class="shrink-0" @click="openLightbox(document)">
                                                        <img :src="document.file_url ?? undefined" :alt="document.title" class="h-12 w-12 rounded-md object-cover ring-1 ring-border" loading="lazy" />
                                                    </button>
                                                    <a v-else-if="document.file_url" :href="documentFileUrl(document) ?? undefined" target="_blank" rel="noopener" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-background ring-1 ring-border">
                                                        <FileText v-if="document.preview_kind === 'pdf'" class="h-6 w-6 text-primary" />
                                                        <FileQuestion v-else class="h-6 w-6 text-muted-foreground" />
                                                    </a>
                                                    <div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-background ring-1 ring-border">
                                                        <StickyNote class="h-6 w-6 text-muted-foreground" />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="truncate font-medium">{{ document.title }}</div>
                                                        <div class="truncate text-xs text-muted-foreground">{{ document.original_filename || document.document_type }}<template v-if="document.size_label"> · {{ document.size_label }}</template></div>
                                                    </div>
                                                    <a v-if="document.file_url" :href="documentFileUrl(document, true) ?? undefined" class="travel-touch inline-flex h-9 w-9 items-center justify-center rounded-md border border-border" aria-label="Download">
                                                        <Download class="h-4 w-4" />
                                                    </a>
                                                </div>
                                                <div v-if="(documentsByReservation.get(reservation.id) ?? []).length === 0" class="rounded-md bg-muted/40 p-3 text-sm text-muted-foreground">
                                                    No files attached yet.
                                                </div>
                                                <div v-if="trip.can_edit" class="rounded-md bg-muted/40 p-3">
                                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" multiple class="text-xs" @change="stageReservationAttachment(reservation.id, $event)" />
                                                    <form v-if="attachmentForm.reservation_id === reservation.id && attachmentForm.files.length" class="mt-3 grid gap-2" @submit.prevent="submitReservationAttachment">
                                                        <div class="text-xs text-muted-foreground">{{ attachmentForm.files.map((file) => file.name).join(', ') }}</div>
                                                        <Input class="travel-touch" v-model="attachmentForm.title_prefix" placeholder="Title prefix (optional)" />
                                                        <Button type="submit" size="sm" class="travel-button-primary" :disabled="attachmentForm.processing">Upload to reservation</Button>
                                                        <InputError :message="attachmentForm.errors.files" />
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <p v-if="isShared && reservation.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ reservation.last_edited_by }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span v-if="reservation.document_count > 0" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-xs text-primary">
                                            <Paperclip class="h-3 w-3" />
                                            {{ reservation.document_count }}
                                        </span>
                                        <span class="rounded-full bg-accent px-2 py-1 text-xs dark:bg-accent">{{ reservation.status }}</span>
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
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyReservation.url({ trip: trip.id, reservation: reservation.id }), reservation.title)">
                                            <Trash2 class="h-4 w-4" />
                                            Delete
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
                            <Button type="submit" class="travel-button-primary" :disabled="reservationForm.processing">Add reservation</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'budget'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Budget</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-md bg-accent p-4 dark:bg-accent">
                                <div class="text-xs uppercase text-muted-foreground dark:text-muted-foreground">Planned</div>
                                <div class="text-2xl font-semibold">${{ plannedTotal.toFixed(2) }}</div>
                            </div>
                            <div class="rounded-md bg-accent p-4 dark:bg-accent">
                                <div class="text-xs uppercase text-muted-foreground dark:text-muted-foreground">Actual</div>
                                <div class="text-2xl font-semibold">${{ actualTotal.toFixed(2) }}</div>
                            </div>
                        </div>
                        <div v-for="cost in trip.costs" :id="`cost-${cost.id}`" :key="cost.id" tabindex="-1" class="rounded-md border border-border p-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border">
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
                                    <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex justify-between gap-3">
                                    <span>{{ cost.label }} · {{ cost.category }}</span>
                                    <div class="flex items-center gap-2">
                                        <span>{{ cost.currency }} {{ cost.actual_amount || cost.planned_amount || '0.00' }}</span>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('cost', cost)">Edit</Button>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyCost.url({ trip: trip.id, cost: cost.id }), cost.label)">
                                            <Trash2 class="h-4 w-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="cost.notes" class="mt-2 rounded-md bg-muted p-2 text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ cost.notes }}</p>
                                <p v-if="isShared && cost.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ cost.last_edited_by }}</p>
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
                            <Button type="submit" class="travel-button-primary" :disabled="costForm.processing">Add cost</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'packing'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader class="gap-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <CardTitle class="text-base">Packing List<span v-if="totalPackingCount"> · {{ packedCount }} of {{ totalPackingCount }} packed</span></CardTitle>
                                <div v-if="totalPackingCount" class="mt-3 space-y-2">
                                    <div class="flex h-2 w-full overflow-hidden rounded-full bg-muted" :title="`${packedPercentage}% packed`">
                                        <div
                                            v-for="bucket in assigneeProgress"
                                            :key="bucket.key"
                                            class="relative h-full border-r border-background last:border-r-0"
                                            :style="{ width: `${(bucket.total / totalPackingCount) * 100}%` }"
                                            :title="`${bucket.label}: ${bucket.packed}/${bucket.total}`"
                                        >
                                            <div class="absolute inset-y-0 left-0 bg-primary transition-all" :style="{ width: `${(bucket.packed / bucket.total) * 100}%` }" />
                                        </div>
                                    </div>
                                    <div v-if="!mineOnly && !hidePacked" class="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <span v-for="bucket in assigneeProgress" :key="`chip-${bucket.key}`" class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1">
                                            <span class="font-medium text-foreground">{{ bucket.initials }}</span>
                                            <span>{{ bucket.packed }}/{{ bucket.total }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="totalPackingCount" class="flex flex-col gap-2 text-sm text-muted-foreground min-[420px]:flex-row sm:flex-col lg:flex-row">
                                <label class="inline-flex min-h-11 items-center gap-2">
                                    <input v-model="mineOnly" type="checkbox" class="rounded border-input" />
                                    Mine to pack
                                </label>
                                <label class="inline-flex min-h-11 items-center gap-2">
                                    <input v-model="hidePacked" type="checkbox" class="rounded border-input" />
                                    Hide packed
                                </label>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div v-if="totalPackingCount === 0 || orderedPackingItems.length === 0" class="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            <CheckCircle2 class="mx-auto size-6 text-primary" />
                            <p class="mt-2">{{ packingEmptyMessage() }}</p>
                        </div>
                        <div
                            v-for="item in orderedPackingItems"
                            :id="`packing-item-${item.id}`"
                            :key="item.id"
                            tabindex="-1"
                            class="rounded-md border border-border p-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border"
                            :class="{ 'bg-muted/40': item.is_packed }"
                        >
                            <form v-if="isEditing('packing', item.id)" class="grid gap-3" @submit.prevent="patchEdit(updatePackingItem.url({ trip: trip.id, packingItem: item.id }))">
                                <Input class="travel-touch" v-model="editData.label" placeholder="Item" />
                                <Input class="travel-touch" v-model="editData.traveler_name" placeholder="Traveler name" />
                                <label class="grid gap-1 text-sm">
                                    <span class="text-muted-foreground">Who packs it?</span>
                                    <select v-model="editData.assigned_to_user_id" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm" :disabled="!trip.can_edit">
                                        <option :value="null">Unassigned (use traveler name)</option>
                                        <option v-for="participant in trip.participants" :key="participant.id" :value="participant.id">
                                            {{ participantOptionLabel(participant) }}
                                        </option>
                                    </select>
                                </label>
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
                                    <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <Checkbox
                                            :model-value="item.is_packed"
                                            :disabled="!trip.can_edit || togglingPackingIds.has(item.id)"
                                            class="mt-0.5 size-5"
                                            :aria-label="`${item.is_packed ? 'Mark' : 'Mark'} ${item.label} as ${item.is_packed ? 'unpacked' : 'packed'}`"
                                            @update:model-value="togglePackedItem(item)"
                                        />
                                        <div class="min-w-0">
                                            <div>
                                                <span :class="item.is_packed ? 'text-muted-foreground line-through' : 'font-medium'">{{ item.quantity }}x {{ item.label }}</span>
                                                <span class="text-muted-foreground dark:text-muted-foreground"> · {{ item.category }}</span>
                                            </div>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span class="inline-flex items-center gap-1">
                                                    <span>added by</span>
                                                    <Avatar v-if="item.added_by" class="size-5" :title="item.added_by.name" :aria-label="`Added by ${item.added_by.name}`">
                                                        <AvatarFallback class="bg-primary/15 text-[10px] font-medium text-primary">{{ item.added_by.initials }}</AvatarFallback>
                                                    </Avatar>
                                                    <span v-else>unknown</span>
                                                </span>
                                                <span class="inline-flex items-center gap-1">
                                                    <span>for</span>
                                                    <Avatar
                                                        class="size-5"
                                                        :class="{ 'ring-1 ring-primary ring-offset-1 ring-offset-background': item.assigned_to_user_id === currentUserId }"
                                                        :title="assigneeLabel(item)"
                                                        :aria-label="`For ${assigneeLabel(item)}`"
                                                    >
                                                        <AvatarFallback class="bg-primary/15 text-[10px] font-medium text-primary">{{ assigneeInitials(item) }}</AvatarFallback>
                                                    </Avatar>
                                                    <span class="max-w-32 truncate" :title="assigneeLabel(item)">{{ item.assigned_to?.first_name ?? item.traveler_name ?? 'anyone' }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('packing', item)">Edit</Button>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyPackingItem.url({ trip: trip.id, packingItem: item.id }), item.label)">
                                            <Trash2 class="h-4 w-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="packingRowErrors[item.id]" class="mt-2 rounded-md border border-destructive/30 bg-destructive/10 p-2 text-sm text-destructive">{{ packingRowErrors[item.id] }}</p>
                                <p v-if="item.notes" class="mt-2 rounded-md bg-muted p-2 text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ item.notes }}</p>
                                <p v-if="isShared && item.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ item.last_edited_by }}</p>
                            </template>
                        </div>
                    </CardContent>
                </Card>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Packing Item</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(packingForm, storePackingItem.url(trip.id), ['traveler_name', 'assigned_to_user_id', 'label', 'notes'])">
                            <Input class="travel-touch" v-model="packingForm.label" placeholder="Item" />
                            <Input class="travel-touch" v-model="packingForm.traveler_name" placeholder="Traveler name" />
                            <label class="grid gap-1 text-sm">
                                <span class="text-muted-foreground">Who packs it?</span>
                                <select v-model="packingForm.assigned_to_user_id" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm" :disabled="!trip.can_edit">
                                    <option :value="null">Unassigned (use traveler name)</option>
                                    <option v-for="participant in trip.participants" :key="participant.id" :value="participant.id">
                                        {{ participantOptionLabel(participant) }}
                                    </option>
                                </select>
                            </label>
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <Input class="travel-touch" v-model="packingForm.category" placeholder="Category" />
                                <Input class="travel-touch" v-model="packingForm.quantity" type="number" min="1" />
                            </div>
                            <textarea v-model="packingForm.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button type="submit" class="travel-button-primary" :disabled="packingForm.processing">Add item</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'tasks'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Tasks · {{ completedTasks }}/{{ trip.tasks.length }} done</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="task in trip.tasks" :id="`task-${task.id}`" :key="task.id" tabindex="-1" class="rounded-md border border-border p-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border">
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
                                    <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <Checkbox
                                            :model-value="Boolean(task.completed_at)"
                                            :disabled="!trip.can_edit || togglingTaskIds.has(task.id)"
                                            class="mt-0.5 size-5"
                                            :aria-label="`${task.completed_at ? 'Reopen' : 'Complete'} ${task.title}`"
                                            @update:model-value="toggleTaskCompletion(task)"
                                        />
                                        <div class="min-w-0">
                                            <div class="font-medium" :class="{ 'text-muted-foreground line-through': task.completed_at }">{{ task.title }}</div>
                                            <div class="text-muted-foreground dark:text-muted-foreground">{{ task.priority }} · due {{ formatDateTime(task.due_at) }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span v-if="task.completed_at" class="rounded-full bg-accent px-2 py-1 text-xs dark:bg-accent">Done</span>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('task', task)">Edit</Button>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyTask.url({ trip: trip.id, task: task.id }), task.title)">
                                            <Trash2 class="h-4 w-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                                <p v-if="taskRowErrors[task.id]" class="mt-2 rounded-md border border-destructive/30 bg-destructive/10 p-2 text-sm text-destructive">{{ taskRowErrors[task.id] }}</p>
                                <p v-if="task.description" class="mt-2 rounded-md bg-muted p-2 text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ task.description }}</p>
                                <p v-if="isShared && task.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ task.last_edited_by }}</p>
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
                            <Button type="submit" class="travel-button-primary" :disabled="taskForm.processing">Add task</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'documents'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Documents</CardTitle></CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            class="rounded-md border-2 border-dashed p-6 text-center transition-colors"
                            :class="dropActive ? 'border-primary bg-primary/5' : 'border-border bg-muted/30'"
                            @dragenter.prevent="dropActive = true"
                            @dragover.prevent="dropActive = true"
                            @dragleave.prevent="dropActive = false"
                            @drop.prevent="handleDrop"
                        >
                            <input ref="fileInputEl" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" multiple class="hidden" @change="handleFileInput" />
                            <Button type="button" class="travel-button-primary" @click="fileInputEl?.click()">
                                <Upload class="h-4 w-4" />
                                Choose files
                            </Button>
                            <p class="mt-2 text-sm text-muted-foreground">PDFs and images up to 15 MB each. Max 10 per upload.</p>
                        </div>

                        <form v-if="uploadForm.files.length" class="grid gap-3 rounded-md border border-border bg-card p-3" @submit.prevent="submitUpload">
                            <div class="space-y-2">
                                <div v-for="(file, index) in uploadForm.files" :key="`${file.name}-${index}`" class="flex items-center gap-2 rounded-md bg-muted px-3 py-2 text-sm">
                                    <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ formatBytes(file.size) }}</span>
                                    <button type="button" class="text-xs font-medium text-destructive" @click="removeStagedFile(index)">Remove</button>
                                </div>
                            </div>
                            <Input class="travel-touch" v-model="uploadForm.title_prefix" placeholder="Title prefix (optional)" />
                            <Input class="travel-touch" v-model="uploadForm.document_type" placeholder="Type (boarding pass, passport, receipt)" />
                            <Input class="travel-touch" v-model="uploadForm.expires_on" type="date" />
                            <textarea v-model="uploadForm.notes" class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes applied to every uploaded file" />
                            <div v-if="uploadForm.progress" class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${uploadForm.progress.percentage}%` }" />
                            </div>
                            <InputError :message="uploadForm.errors.files" />
                            <InputError v-for="error in stagedFileErrors" :key="error" :message="error" />
                            <Button type="submit" class="travel-button-primary" :disabled="uploadForm.processing">Upload {{ uploadForm.files.length }} file{{ uploadForm.files.length === 1 ? '' : 's' }}</Button>
                        </form>

                        <div v-if="trip.documents.length === 0" class="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                            No documents yet.
                        </div>

                        <div v-for="document in trip.documents" :id="`document-${document.id}`" :key="document.id" tabindex="-1" class="rounded-md border border-border p-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border">
                            <form v-if="isEditing('document', document.id)" class="grid gap-3" @submit.prevent="patchEdit(updateDocument.url({ trip: trip.id, document: document.id }))">
                                <Input class="travel-touch" v-model="editData.title" placeholder="Document title" />
                                <Input class="travel-touch" v-model="editData.document_type" placeholder="Type" />
                                <Input class="travel-touch" v-model="editData.expires_on" type="date" />
                                <select v-model="editData.reservation_id" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                    <option :value="null">Not linked to a reservation</option>
                                    <option v-for="reservation in trip.reservations" :key="reservation.id" :value="reservation.id">{{ reservation.title }} ({{ reservation.type }})</option>
                                </select>
                                <textarea v-model="editData.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                <div class="flex gap-2">
                                    <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                    <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                </div>
                            </form>
                            <template v-else>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                                    <button v-if="document.preview_kind === 'image'" type="button" class="shrink-0" @click="openLightbox(document)">
                                        <img :src="document.file_url ?? undefined" :alt="document.title" class="h-20 w-20 rounded-md object-cover ring-1 ring-border" loading="lazy" />
                                    </button>
                                    <a v-else-if="document.file_url" :href="documentFileUrl(document) ?? undefined" :target="document.preview_kind === 'pdf' ? '_blank' : undefined" rel="noopener" class="flex h-20 w-20 shrink-0 items-center justify-center rounded-md bg-muted ring-1 ring-border">
                                        <FileText v-if="document.preview_kind === 'pdf'" class="h-9 w-9 text-primary" />
                                        <Image v-else-if="document.preview_kind === 'image-opaque'" class="h-9 w-9 text-muted-foreground" />
                                        <FileQuestion v-else class="h-9 w-9 text-muted-foreground" />
                                    </a>
                                    <div v-else class="flex h-20 w-20 shrink-0 items-center justify-center rounded-md bg-muted ring-1 ring-border">
                                        <StickyNote class="h-9 w-9 text-muted-foreground" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium">{{ document.title }}</div>
                                        <div class="text-muted-foreground dark:text-muted-foreground">
                                            {{ document.document_type }}
                                            <template v-if="document.expires_on"> · expires {{ formatDate(document.expires_on) }}</template>
                                            <template v-if="document.size_label"> · {{ document.size_label }}</template>
                                        </div>
                                        <div v-if="document.original_filename" class="mt-1 truncate text-xs text-muted-foreground">{{ document.original_filename }}</div>
                                        <div v-if="document.reservation_id" class="mt-1 text-xs text-muted-foreground">Linked to {{ reservationTitleFor(document.reservation_id) }}</div>
                                        <p v-if="document.notes" class="mt-2 rounded-md bg-muted p-2 text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ document.notes }}</p>
                                        <p v-if="isShared && document.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ document.last_edited_by }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <a v-if="document.file_url" :href="documentFileUrl(document, true) ?? undefined" class="travel-touch inline-flex h-9 items-center gap-2 rounded-md border border-border px-3 text-xs font-medium">
                                            <Download class="h-4 w-4" />
                                            Download
                                        </a>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('document', document)">Edit</Button>
                                        <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyDocument.url({ trip: trip.id, document: document.id }), document.title)">
                                            <Trash2 class="h-4 w-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </CardContent>
                </Card>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Add Note Instead</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="post(documentForm, storeDocument.url(trip.id), ['title', 'expires_on', 'notes', 'reservation_id'])">
                            <Input class="travel-touch" v-model="documentForm.title" placeholder="Document title" />
                            <Input class="travel-touch" v-model="documentForm.document_type" placeholder="Type" />
                            <Input class="travel-touch" v-model="documentForm.expires_on" type="date" />
                            <select v-model="documentForm.reservation_id" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option :value="null">Not linked to a reservation</option>
                                <option v-for="reservation in trip.reservations" :key="reservation.id" :value="reservation.id">{{ reservation.title }} ({{ reservation.type }})</option>
                            </select>
                            <textarea v-model="documentForm.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <Button type="submit" class="travel-button-primary" :disabled="documentForm.processing">Add note</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'imports'" class="grid gap-4 lg:grid-cols-[1fr_24rem]">
                <div class="space-y-4">
                    <Card class="travel-panel">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base"><Sparkles class="h-5 w-5 text-primary" /> Automation Suggestions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="trip.automation_suggestions.length" class="space-y-2">
                                <div v-for="suggestion in trip.automation_suggestions" :key="suggestion.id" class="rounded-md border border-border p-3 dark:border-border">
                                    <div class="text-sm font-semibold">{{ suggestion.summary }}</div>
                                    <div class="mt-3 flex gap-2">
                                        <Button size="sm" class="travel-button-primary" @click="simplePost(`/trips/${trip.id}/automation/${suggestion.id}/accept`)">Accept</Button>
                                        <Button size="sm" class="travel-touch" variant="outline" @click="simplePost(`/trips/${trip.id}/automation/${suggestion.id}/dismiss`)">Dismiss</Button>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground dark:text-muted-foreground">No active suggestions. Refresh after adding or importing details.</p>
                            <Button class="travel-touch" variant="outline" @click="simplePost(`/trips/${trip.id}/automation/refresh`)">
                                <Sparkles class="h-4 w-4" />
                                Refresh suggestions
                            </Button>
                        </CardContent>
                    </Card>

                    <Card class="travel-panel">
                        <CardHeader><CardTitle class="text-base">Import Review Queue</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="batch in trip.import_batches" :key="batch.id" class="rounded-md border border-border p-3 text-sm dark:border-border">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold">{{ batch.source_type.toUpperCase() }} import</div>
                                        <div class="text-muted-foreground dark:text-muted-foreground">{{ batch.status }} · {{ (batch.parsed_payload?.items || []).length }} parsed item(s)</div>
                                    </div>
                                    <span class="rounded-full bg-accent px-2 py-1 text-xs dark:bg-accent">{{ batch.status }}</span>
                                </div>
                                <div v-if="batch.status === 'reviewing'" class="mt-3 flex flex-wrap gap-2">
                                    <Button size="sm" class="travel-button-primary" @click="simplePost(`/trips/${trip.id}/imports/${batch.id}/commit`)">Commit</Button>
                                    <Button size="sm" class="travel-touch" variant="outline" @click="simplePost(`/trips/${trip.id}/imports/${batch.id}/discard`)">Discard</Button>
                                </div>
                            </div>
                            <p v-if="!trip.import_batches.length" class="text-sm text-muted-foreground dark:text-muted-foreground">No imports yet.</p>
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
                            <Button type="submit" class="travel-button-primary" :disabled="importForm.processing">Parse for review</Button>
                        </form>
                    </CardContent>
                </Card>
            </section>

            <section v-if="activePanel === 'sharing' || activePanel === 'reminders'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Users class="h-5 w-5" /> Shared Travelers</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="collaborator in trip.collaborators" :id="`collaborator-${collaborator.id}`" :key="collaborator.id" tabindex="-1" class="flex items-center justify-between rounded-md border border-border p-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border">
                            <div>
                                <div class="font-medium">{{ collaborator.email }}</div>
                                <div class="text-muted-foreground dark:text-muted-foreground">{{ collaborator.role }} · {{ collaborator.accepted_at ? 'accepted' : 'pending' }}</div>
                            </div>
                            <Button v-if="trip.can_share" size="sm" class="travel-touch" variant="outline" @click="destroyCollaborator(collaborator.id)">Remove</Button>
                        </div>
                        <p v-if="!trip.collaborators.length" class="text-sm text-muted-foreground dark:text-muted-foreground">No collaborators yet.</p>
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
                                <Button type="submit" class="travel-button-primary" :disabled="!trip.can_share || collaboratorForm.processing">Invite</Button>
                            </form>
                        </CardContent>
                    </Card>
                    <Card class="travel-panel">
                        <CardHeader><CardTitle class="flex items-center gap-2 text-base"><Bell class="h-5 w-5" /> Reminders</CardTitle></CardHeader>
                        <CardContent class="space-y-2">
                            <div v-for="reminder in trip.reminders" :id="`reminder-${reminder.id}`" :key="reminder.id" tabindex="-1" class="rounded-md border border-border p-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border">
                                <form v-if="isEditing('reminder', reminder.id)" class="grid gap-3" @submit.prevent="patchEdit(updateReminder.url({ trip: trip.id, reminder: reminder.id }))">
                                    <Input class="travel-touch" v-model="editData.label" placeholder="Reminder label" />
                                    <Input class="travel-touch" v-model="editData.remind_at" type="datetime-local" />
                                    <Input class="travel-touch" v-model="editData.timezone" placeholder="Timezone" />
                                    <textarea v-model="editData.notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                    <div class="flex gap-2">
                                        <Button size="sm" type="submit" class="travel-button-primary">Save</Button>
                                        <Button size="sm" type="button" variant="outline" class="travel-touch" @click="cancelEdit">Cancel</Button>
                                    </div>
                                </form>
                                <template v-else>
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-medium">{{ reminder.label }}</div>
                                            <div class="text-muted-foreground dark:text-muted-foreground">{{ formatDateTime(reminder.remind_at, reminder.timezone) }}</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Button v-if="trip.can_edit" size="sm" type="button" variant="outline" class="travel-touch" @click="startEdit('reminder', reminder)">Edit</Button>
                                            <Button v-if="trip.can_edit" size="sm" type="button" variant="destructive" class="travel-touch" @click="destroyEntry(destroyReminder.url({ trip: trip.id, reminder: reminder.id }), reminder.label)">
                                                <Trash2 class="h-4 w-4" />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                    <p v-if="reminder.notes" class="mt-2 rounded-md bg-muted p-2 text-muted-foreground dark:bg-muted dark:text-muted-foreground">{{ reminder.notes }}</p>
                                    <p v-if="isShared && reminder.last_edited_by" class="mt-2 text-xs italic text-muted-foreground dark:text-muted-foreground">Last edited by {{ reminder.last_edited_by }}</p>
                                </template>
                            </div>
                            <p v-if="!trip.reminders.length" class="text-sm text-muted-foreground dark:text-muted-foreground">No reminders yet.</p>
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
                                <Button type="submit" class="travel-button-primary" :disabled="reminderForm.processing">Add reminder</Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>

        <div
            v-if="lightboxDocument"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeLightbox"
            @keydown.esc="closeLightbox"
        >
            <button type="button" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Close preview" @click="closeLightbox">
                <X class="h-6 w-6" />
            </button>
            <img :src="lightboxDocument.file_url ?? undefined" :alt="lightboxDocument.title" class="max-h-full max-w-full rounded-md object-contain" />
            <div class="absolute bottom-4 left-1/2 max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-full bg-black/70 px-4 py-2 text-sm text-white">
                {{ lightboxDocument.title }}<template v-if="lightboxDocument.size_label"> · {{ lightboxDocument.size_label }}</template>
            </div>
        </div>
    </div>
</template>
