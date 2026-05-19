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
import { toast } from 'vue-sonner';
import FormErrorSummary from '@/components/FormErrorSummary.vue';
import InputError from '@/components/InputError.vue';
import TimezonePicker from '@/components/TimezonePicker.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTripRealtime } from '@/composables/useTripRealtime';
import { formatTripDate, formatTripDateTime, timezoneLabel } from '@/lib/dates';
import { scrollToFirstError } from '@/lib/scrollToFirstError';
import { update as updateTrip } from '@/routes/trips';
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

type FlightDetails = {
    id: number | null;
    reservation_id: number | null;
    cabin_class: 'economy' | 'premium_economy' | 'business' | 'first' | null;
    currency: string | null;
    carry_on_size: string | null;
    carry_on_weight: string | null;
    carry_on_fee: string | null;
    personal_item_size: string | null;
    personal_item_weight: string | null;
    personal_item_fee: string | null;
    checked_bag_size: string | null;
    checked_bag_weight: string | null;
    checked_bag_fee: string | null;
    additional_checked_bag_fee: string | null;
    additional_checked_bag_allowance: string | null;
    visa_requirement: string | null;
    passport_validity_rule: string | null;
    layover_notes: string | null;
    online_check_in_opens: string | null;
    boarding_closes: string | null;
    notes: string | null;
};

type ReservationItem = Record<string, any> & {
    id: number;
    title: string;
    type: string;
    document_count: number;
    flight_details: FlightDetails | null;
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
    destination_timezone: string | null;
    home_timezone: string | null;
    effective_destination_timezone: string;
    effective_home_timezone: string;
    timezone_guess: string | null;
    starts_on: string;
    ends_on: string;
    status: string;
    summary: string | null;
    cover_theme: string;
    length: string;
    can_edit: boolean;
    can_share: boolean;
    suggested_currency: string;
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
        kind: string;
        is_day_one_anchor: boolean;
        label: string;
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

const props = defineProps<{ trip: Trip; timezones: string[] }>();
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
const defaultReservationTimezone = computed(() => props.trip.effective_destination_timezone ?? props.trip.effective_home_timezone ?? timezone);
type EditKind = 'itinerary' | 'reservation' | 'cost' | 'packing' | 'task' | 'document' | 'reminder';
const editing = ref<{ type: EditKind; id: number } | null>(null);
const editData = ref<Record<string, any>>({});
const reservationTimezonesLinked = ref(true);
const editReservationTimezonesLinked = ref(true);
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
const flightDetailsExpanded = ref(new Set<number>());
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
    starts_timezone: defaultReservationTimezone.value,
    ends_at: '',
    ends_timezone: defaultReservationTimezone.value,
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

const tripForm = useForm({
    name: props.trip.name,
    destination: props.trip.destination,
    destination_timezone: props.trip.destination_timezone ?? props.trip.timezone_guess,
    home_timezone: props.trip.home_timezone ?? props.trip.effective_home_timezone,
    starts_on: props.trip.starts_on,
    ends_on: props.trip.ends_on,
    status: props.trip.status,
    summary: props.trip.summary ?? '',
    cover_theme: props.trip.cover_theme,
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
    { id: 'details', label: 'Details', icon: StickyNote },
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
const itineraryDateTimeMin = computed(() => `${props.trip.starts_on}T00:00`);
const editErrors = computed<Record<string, string>>(() => {
    if (!editing.value) {
        return {};
    }

    return page.props.errors as Record<string, string>;
});
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
        onError: (errors) => {
            scrollToFirstError(errors);
            toast.error("Couldn't save - check the highlighted fields.");
        },
    });
};

const isEditing = (type: EditKind, id: number) => editing.value?.type === type && editing.value.id === id;

const toDateTimeLocal = (value: string | null) => value ? new Date(value).toISOString().slice(0, 16) : '';

const flightDetailsKeys: (keyof FlightDetails)[] = [
    'cabin_class',
    'currency',
    'carry_on_size',
    'carry_on_weight',
    'carry_on_fee',
    'personal_item_size',
    'personal_item_weight',
    'personal_item_fee',
    'checked_bag_size',
    'checked_bag_weight',
    'checked_bag_fee',
    'additional_checked_bag_fee',
    'additional_checked_bag_allowance',
    'visa_requirement',
    'passport_validity_rule',
    'layover_notes',
    'online_check_in_opens',
    'boarding_closes',
    'notes',
];

const blankFlightDetails = (suggestedCurrency: string): FlightDetails => ({
    id: null,
    reservation_id: null,
    cabin_class: null,
    currency: suggestedCurrency,
    carry_on_size: null,
    carry_on_weight: null,
    carry_on_fee: null,
    personal_item_size: null,
    personal_item_weight: null,
    personal_item_fee: null,
    checked_bag_size: null,
    checked_bag_weight: null,
    checked_bag_fee: null,
    additional_checked_bag_fee: null,
    additional_checked_bag_allowance: null,
    visa_requirement: null,
    passport_validity_rule: null,
    layover_notes: null,
    online_check_in_opens: null,
    boarding_closes: null,
    notes: null,
});

const hasAnyFlightDetailsValue = (flightDetails: FlightDetails | null | undefined): boolean => {
    if (!flightDetails) {
        return false;
    }

    return flightDetailsKeys.some((key) => {
        const value = flightDetails[key];

        return value !== null && value !== '' && value !== undefined;
    });
};

const filledFlightDetailsCount = (flightDetails: FlightDetails | null | undefined): number => {
    if (!flightDetails) {
        return 0;
    }

    return flightDetailsKeys.filter((key) => {
        const value = flightDetails[key];

        return value !== null && value !== '' && value !== undefined;
    }).length;
};

const normalizeFlightDetailsForSubmit = (flightDetails: FlightDetails | null | undefined): Record<string, unknown> | null => {
    if (!flightDetails) {
        return null;
    }

    return Object.fromEntries(flightDetailsKeys.map((key) => [key, flightDetails[key] === '' ? null : flightDetails[key]]));
};

const ensureReservationFlightDetails = () => {
    if (editData.value.type === 'flight' && !editData.value.flight_details) {
        editData.value.flight_details = blankFlightDetails(props.trip.suggested_currency);
    }
};

const uppercaseFlightDetailsCurrency = () => {
    const currency = editData.value.flight_details?.currency;

    editData.value.flight_details.currency = currency ? currency.toUpperCase() : null;
};

const cabinLabel = (value: string): string => ({
    economy: 'Economy',
    premium_economy: 'Premium Economy',
    business: 'Business',
    first: 'First',
}[value] ?? value);

const formatBagSummary = (label: string, size: string | null, weight: string | null): string => {
    const dimensions = [size, weight].filter(Boolean).join(' ');

    return dimensions ? `${label} ${dimensions}` : label;
};

const formatPrice = (amount: string | null, currency: string | null): string => {
    if (!amount) {
        return '';
    }

    return currency ? `${amount} ${currency}` : amount;
};

const flightDetailsSummary = (flightDetails: FlightDetails | null | undefined): string | null => {
    if (!flightDetails) {
        return null;
    }

    const parts: string[] = [];

    if (flightDetails.cabin_class) {
        parts.push(cabinLabel(flightDetails.cabin_class));
    }

    if (flightDetails.carry_on_size || flightDetails.carry_on_weight) {
        parts.push(formatBagSummary('Carry-on', flightDetails.carry_on_size, flightDetails.carry_on_weight));
    }

    if (flightDetails.checked_bag_size || flightDetails.checked_bag_weight || flightDetails.checked_bag_fee) {
        const checked = formatBagSummary('Checked', flightDetails.checked_bag_size, flightDetails.checked_bag_weight);
        const fee = flightDetails.checked_bag_fee ? formatPrice(flightDetails.checked_bag_fee, flightDetails.currency) : null;
        parts.push(fee ? `${checked} (${fee})` : checked);
    }

    return parts.length ? parts.join(' · ') : null;
};

const hasExpandableFlightDetails = (flightDetails: FlightDetails | null | undefined): boolean => {
    if (!flightDetails) {
        return false;
    }

    return Boolean(
        flightDetails.personal_item_size
        || flightDetails.personal_item_weight
        || flightDetails.personal_item_fee
        || flightDetails.carry_on_fee
        || flightDetails.additional_checked_bag_fee
        || flightDetails.additional_checked_bag_allowance
        || flightDetails.visa_requirement
        || flightDetails.passport_validity_rule
        || flightDetails.layover_notes
        || flightDetails.online_check_in_opens
        || flightDetails.boarding_closes
        || flightDetails.notes,
    );
};

const hasAnyBaggageInfo = (flightDetails: FlightDetails): boolean => Boolean(
    flightDetails.carry_on_size
    || flightDetails.carry_on_weight
    || flightDetails.carry_on_fee
    || flightDetails.personal_item_size
    || flightDetails.personal_item_weight
    || flightDetails.personal_item_fee
    || flightDetails.checked_bag_size
    || flightDetails.checked_bag_weight
    || flightDetails.checked_bag_fee
    || flightDetails.additional_checked_bag_fee
    || flightDetails.additional_checked_bag_allowance,
);

const toggleFlightDetails = (reservationId: number) => {
    const next = new Set(flightDetailsExpanded.value);

    if (next.has(reservationId)) {
        next.delete(reservationId);
    } else {
        next.add(reservationId);
    }

    flightDetailsExpanded.value = next;
};

const isFlightDetailsExpanded = (reservationId: number): boolean => flightDetailsExpanded.value.has(reservationId);

const startEdit = (type: EditKind, entry: Record<string, any>) => {
    const normalized = { ...entry };

    ['starts_at', 'ends_at', 'due_at', 'remind_at', 'completed_at'].forEach((key) => {
        if (key in normalized) {
            normalized[key] = toDateTimeLocal(normalized[key]);
        }
    });

    editing.value = { type, id: entry.id };
    editData.value = normalized;
    editReservationTimezonesLinked.value = type === 'reservation'
        ? normalized.starts_timezone === normalized.ends_timezone
        : true;
};

const cancelEdit = () => {
    editing.value = null;
    editData.value = {};
};

const patchEdit = (url: string) => {
    const payload = editing.value?.type === 'reservation'
        ? {
            ...editData.value,
            flight_details: normalizeFlightDetailsForSubmit(editData.value.flight_details),
        }
        : editData.value;

    router.patch(url, payload, {
        preserveScroll: true,
        onSuccess: cancelEdit,
        onError: (errors) => {
            scrollToFirstError(errors);
            toast.error("Couldn't save - check the highlighted fields.");
        },
    });
};

const editError = (field: string): string | undefined => editErrors.value[field];

watch(() => reservationForm.starts_timezone, (value) => {
    if (reservationTimezonesLinked.value) {
        reservationForm.ends_timezone = value;
    }
});

watch(reservationTimezonesLinked, (linked) => {
    if (linked) {
        reservationForm.ends_timezone = reservationForm.starts_timezone;
    }
});

watch(() => editData.value.starts_timezone, (value) => {
    if (editing.value?.type === 'reservation' && editReservationTimezonesLinked.value) {
        editData.value.ends_timezone = value;
    }
});

watch(editReservationTimezonesLinked, (linked) => {
    if (linked && editing.value?.type === 'reservation') {
        editData.value.ends_timezone = editData.value.starts_timezone;
    }
});

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

const removeStagedAttachment = (index: number) => {
    attachmentForm.files = attachmentForm.files.filter((_, currentIndex) => currentIndex !== index);

    if (attachmentForm.files.length === 0) {
        attachmentForm.reservation_id = null;
    }
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

const effectiveDestinationTimezone = computed(() => props.trip.effective_destination_timezone || 'UTC');
const effectiveHomeTimezone = computed(() => props.trip.effective_home_timezone || 'UTC');
const showTimezoneContext = computed(() => effectiveDestinationTimezone.value !== effectiveHomeTimezone.value);
const destinationTimezoneMissing = computed(() => props.trip.destination_timezone === null && props.trip.can_edit);

const formatDate = (value: string | null) => formatTripDate(value, effectiveDestinationTimezone.value);
const formatDateTime = (value: string | null, timeZone?: string | null) => formatTripDateTime(value, timeZone);

const submitTripDetails = () => {
    tripForm.patch(updateTrip.url(props.trip.id), { preserveScroll: true });
};
</script>

<template>
    <div>
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
                        <p class="travel-muted mt-1 text-xs">
                            in {{ effectiveDestinationTimezone }} time ({{ timezoneLabel(effectiveDestinationTimezone) }})
                            <template v-if="showTimezoneContext">
                                · home time {{ effectiveHomeTimezone }} ({{ timezoneLabel(effectiveHomeTimezone) }})
                            </template>
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

            <Alert v-if="destinationTimezoneMissing">
                <AlertDescription class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <span>Set the timezone for {{ trip.destination }} so trip days and calendar dates stay accurate.</span>
                    <Button type="button" size="sm" variant="outline" class="travel-touch" @click="activePanel = 'details'">Set timezone</Button>
                </AlertDescription>
            </Alert>

            <section v-if="activePanel === 'itinerary'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <div class="space-y-4">
                    <Card v-for="day in trip.days" :key="day.id" class="travel-panel">
                        <CardHeader>
                            <CardTitle class="text-base">
                                <span>
                                    <span :class="day.kind?.startsWith('travel') ? 'text-muted-foreground' : 'text-foreground'">{{ day.label }}</span>
                                    <span class="text-muted-foreground"> · {{ formatDate(day.date) }}</span>
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="day.items.length || day.tasks.length" class="space-y-2">
                                <div v-for="item in day.items" :id="`itinerary-item-${item.id}`" :key="item.id" tabindex="-1" class="rounded-md border border-border p-3 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border">
                                    <template v-if="isEditing('itinerary', item.id)">
                                        <form class="grid gap-3" @submit.prevent="patchEdit(updateItineraryItem.url({ trip: trip.id, itineraryItem: item.id }))">
                                            <select v-model="editData.trip_day_id" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                                <option :value="null">Unscheduled idea</option>
                                                <option v-for="optionDay in trip.days" :key="optionDay.id" :value="optionDay.id">{{ optionDay.label }} · {{ optionDay.date }}</option>
                                            </select>
                                            <Input class="travel-touch" v-model="editData.title" placeholder="Title" />
                                            <Input class="travel-touch" v-model="editData.location_name" placeholder="Location" />
                                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                                <div>
                                                    <Input class="travel-touch" v-model="editData.starts_at" type="datetime-local" :min="itineraryDateTimeMin" />
                                                    <InputError :message="editError('starts_at')" />
                                                </div>
                                                <div>
                                                    <Input class="travel-touch" v-model="editData.ends_at" type="datetime-local" :min="itineraryDateTimeMin" />
                                                    <InputError :message="editError('ends_at')" />
                                                </div>
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
                                <div>
                                    <Input class="travel-touch" v-model="itineraryForm.starts_at" type="datetime-local" :min="itineraryDateTimeMin" />
                                    <InputError :message="itineraryForm.errors.starts_at" />
                                </div>
                                <div>
                                    <Input class="travel-touch" v-model="itineraryForm.ends_at" type="datetime-local" :min="itineraryDateTimeMin" />
                                    <InputError :message="itineraryForm.errors.ends_at" />
                                </div>
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
                                    <FormErrorSummary :errors="editErrors" />
                                    <select v-model="editData.type" name="type" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm" @change="ensureReservationFlightDetails">
                                        <option value="flight">Flight</option>
                                        <option value="lodging">Hotel / lodging</option>
                                        <option value="transport">Ground transport</option>
                                        <option value="activity">Activity</option>
                                        <option value="dining">Dining</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                    <InputError :message="editError('type')" />
                                    <Input class="travel-touch" v-model="editData.title" name="title" placeholder="Reservation title" />
                                    <InputError :message="editError('title')" />
                                    <Input class="travel-touch" v-model="editData.provider_name" name="provider_name" placeholder="Provider" />
                                    <InputError :message="editError('provider_name')" />
                                    <Input class="travel-touch" v-model="editData.booking_reference" name="booking_reference" placeholder="Confirmation number" />
                                    <InputError :message="editError('booking_reference')" />
                                    <div class="grid gap-3 min-[460px]:grid-cols-2">
                                        <div>
                                            <Input class="travel-touch" v-model="editData.starts_at" name="starts_at" type="datetime-local" />
                                            <InputError :message="editError('starts_at')" />
                                        </div>
                                        <div>
                                            <Input class="travel-touch" v-model="editData.ends_at" name="ends_at" type="datetime-local" />
                                            <InputError :message="editError('ends_at')" />
                                        </div>
                                    </div>
                                    <div class="grid gap-3 min-[460px]:grid-cols-2">
                                        <div>
                                            <TimezonePicker v-model="editData.starts_timezone" :timezones="timezones" label="Start timezone" error-target="starts_timezone" placeholder="Start timezone" />
                                            <InputError :message="editError('starts_timezone')" />
                                        </div>
                                        <div>
                                            <TimezonePicker v-if="!editReservationTimezonesLinked" v-model="editData.ends_timezone" :timezones="timezones" label="End timezone" error-target="ends_timezone" placeholder="End timezone" />
                                            <div v-else class="travel-touch flex items-center rounded-md border border-input px-3 text-sm text-muted-foreground" data-error-target="ends_timezone">Same as start: {{ editData.starts_timezone }}</div>
                                            <InputError :message="editError('ends_timezone')" />
                                        </div>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                        <input v-model="editReservationTimezonesLinked" type="checkbox" class="rounded border-input" />
                                        Same end timezone as start
                                    </label>
                                    <select v-model="editData.status" name="status" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                        <option value="researching">Researching</option>
                                        <option value="reserved">Reserved</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="checked_in">Checked in</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                    <InputError :message="editError('status')" />
                                    <template v-if="editData.type === 'flight'">
                                        <div class="grid gap-3 min-[460px]:grid-cols-2">
                                            <div>
                                                <Input class="travel-touch" v-model="editData.airline" name="airline" placeholder="Airline" />
                                                <InputError :message="editError('airline')" />
                                            </div>
                                            <div>
                                                <Input class="travel-touch" v-model="editData.flight_number" name="flight_number" placeholder="Flight #" />
                                                <InputError :message="editError('flight_number')" />
                                            </div>
                                        </div>
                                        <div class="grid gap-3 min-[460px]:grid-cols-2">
                                            <div>
                                                <Input class="travel-touch" v-model="editData.departure_airport" name="departure_airport" placeholder="From airport" />
                                                <InputError :message="editError('departure_airport')" />
                                            </div>
                                            <div>
                                                <Input class="travel-touch" v-model="editData.arrival_airport" name="arrival_airport" placeholder="To airport" />
                                                <InputError :message="editError('arrival_airport')" />
                                            </div>
                                        </div>
                                    </template>
                                    <template v-if="editData.type === 'lodging'">
                                        <Input class="travel-touch" v-model="editData.property_name" name="property_name" placeholder="Property name" />
                                        <InputError :message="editError('property_name')" />
                                        <Input class="travel-touch" v-model="editData.room_type" name="room_type" placeholder="Room type" />
                                        <InputError :message="editError('room_type')" />
                                    </template>
                                    <Input class="travel-touch" v-model="editData.location_name" name="location_name" placeholder="Location" />
                                    <InputError :message="editError('location_name')" />
                                    <Input class="travel-touch" v-model="editData.address" name="address" placeholder="Address" />
                                    <InputError :message="editError('address')" />
                                    <div class="grid gap-3 min-[460px]:grid-cols-2">
                                        <div>
                                            <Input class="travel-touch" v-model="editData.contact_phone" name="contact_phone" placeholder="Phone" />
                                            <InputError :message="editError('contact_phone')" />
                                        </div>
                                        <div>
                                            <Input class="travel-touch" v-model="editData.contact_email" name="contact_email" placeholder="Email" />
                                            <InputError :message="editError('contact_email')" />
                                        </div>
                                    </div>
                                    <textarea v-model="editData.notes" name="notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                                    <InputError :message="editError('notes')" />
                                    <details v-if="editData.type === 'flight' && editData.flight_details" class="rounded-md border border-border p-3" :open="hasAnyFlightDetailsValue(editData.flight_details)">
                                        <summary class="cursor-pointer text-sm font-medium">
                                            <span class="inline-flex items-center gap-2">
                                                <Plane class="h-4 w-4" />
                                                Flight details
                                                <span class="text-xs text-muted-foreground">({{ filledFlightDetailsCount(editData.flight_details) }} filled)</span>
                                            </span>
                                        </summary>
                                        <div class="mt-4 grid gap-4">
                                            <fieldset class="grid gap-2">
                                                <legend class="text-xs font-medium uppercase text-muted-foreground">Cabin &amp; pricing</legend>
                                                <div class="grid gap-2 min-[460px]:grid-cols-2">
                                                    <select v-model="editData.flight_details.cabin_class" name="flight_details.cabin_class" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                                        <option :value="null">Cabin class</option>
                                                        <option value="economy">Economy</option>
                                                        <option value="premium_economy">Premium Economy</option>
                                                        <option value="business">Business</option>
                                                        <option value="first">First</option>
                                                    </select>
                                                    <Input class="travel-touch uppercase" v-model="editData.flight_details.currency" name="flight_details.currency" maxlength="3" placeholder="Currency" @blur="uppercaseFlightDetailsCurrency" />
                                                </div>
                                                <InputError :message="editError('flight_details.cabin_class') || editError('flight_details.currency')" />
                                            </fieldset>

                                            <fieldset class="grid gap-3">
                                                <legend class="text-xs font-medium uppercase text-muted-foreground">Baggage allowance</legend>
                                                <div class="grid gap-2 rounded-md bg-muted/30 p-2">
                                                    <div class="text-xs font-medium">Carry-on</div>
                                                    <div class="grid gap-2 min-[460px]:grid-cols-3">
                                                        <Input class="travel-touch" v-model="editData.flight_details.carry_on_size" name="flight_details.carry_on_size" placeholder="Size" />
                                                        <Input class="travel-touch" v-model="editData.flight_details.carry_on_weight" name="flight_details.carry_on_weight" placeholder="Weight" />
                                                        <div class="relative">
                                                            <Input class="travel-touch pr-12" v-model="editData.flight_details.carry_on_fee" name="flight_details.carry_on_fee" type="number" step="0.01" min="0" placeholder="Fee" />
                                                            <span v-if="editData.flight_details.currency" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">{{ editData.flight_details.currency }}</span>
                                                        </div>
                                                    </div>
                                                    <InputError :message="editError('flight_details.carry_on_size') || editError('flight_details.carry_on_weight') || editError('flight_details.carry_on_fee')" />
                                                </div>
                                                <div class="grid gap-2 rounded-md bg-muted/30 p-2">
                                                    <div class="text-xs font-medium">Personal item / extra carry</div>
                                                    <div class="grid gap-2 min-[460px]:grid-cols-3">
                                                        <Input class="travel-touch" v-model="editData.flight_details.personal_item_size" name="flight_details.personal_item_size" placeholder="Size" />
                                                        <Input class="travel-touch" v-model="editData.flight_details.personal_item_weight" name="flight_details.personal_item_weight" placeholder="Weight" />
                                                        <div class="relative">
                                                            <Input class="travel-touch pr-12" v-model="editData.flight_details.personal_item_fee" name="flight_details.personal_item_fee" type="number" step="0.01" min="0" placeholder="Fee" />
                                                            <span v-if="editData.flight_details.currency" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">{{ editData.flight_details.currency }}</span>
                                                        </div>
                                                    </div>
                                                    <InputError :message="editError('flight_details.personal_item_size') || editError('flight_details.personal_item_weight') || editError('flight_details.personal_item_fee')" />
                                                </div>
                                                <div class="grid gap-2 rounded-md bg-muted/30 p-2">
                                                    <div class="text-xs font-medium">Checked bag</div>
                                                    <div class="grid gap-2 min-[460px]:grid-cols-3">
                                                        <Input class="travel-touch" v-model="editData.flight_details.checked_bag_size" name="flight_details.checked_bag_size" placeholder="Size" />
                                                        <Input class="travel-touch" v-model="editData.flight_details.checked_bag_weight" name="flight_details.checked_bag_weight" placeholder="Weight" />
                                                        <div class="relative">
                                                            <Input class="travel-touch pr-12" v-model="editData.flight_details.checked_bag_fee" name="flight_details.checked_bag_fee" type="number" step="0.01" min="0" placeholder="Fee" />
                                                            <span v-if="editData.flight_details.currency" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">{{ editData.flight_details.currency }}</span>
                                                        </div>
                                                    </div>
                                                    <InputError :message="editError('flight_details.checked_bag_size') || editError('flight_details.checked_bag_weight') || editError('flight_details.checked_bag_fee')" />
                                                </div>
                                                <div class="grid gap-2 min-[460px]:grid-cols-2">
                                                    <div>
                                                        <Input class="travel-touch" v-model="editData.flight_details.additional_checked_bag_fee" name="flight_details.additional_checked_bag_fee" type="number" step="0.01" min="0" placeholder="Extra checked bag fee" />
                                                        <InputError :message="editError('flight_details.additional_checked_bag_fee')" />
                                                    </div>
                                                    <div>
                                                        <Input class="travel-touch" v-model="editData.flight_details.additional_checked_bag_allowance" name="flight_details.additional_checked_bag_allowance" placeholder="Extra checked allowance" />
                                                        <InputError :message="editError('flight_details.additional_checked_bag_allowance')" />
                                                    </div>
                                                </div>
                                            </fieldset>

                                            <fieldset class="grid gap-2">
                                                <legend class="text-xs font-medium uppercase text-muted-foreground">Travel documents</legend>
                                                <textarea v-model="editData.flight_details.visa_requirement" name="flight_details.visa_requirement" class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Visa requirement" />
                                                <InputError :message="editError('flight_details.visa_requirement')" />
                                                <textarea v-model="editData.flight_details.passport_validity_rule" name="flight_details.passport_validity_rule" class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Passport validity rule" />
                                                <InputError :message="editError('flight_details.passport_validity_rule')" />
                                            </fieldset>

                                            <fieldset class="grid gap-2">
                                                <legend class="text-xs font-medium uppercase text-muted-foreground">Connection &amp; check-in</legend>
                                                <textarea v-model="editData.flight_details.layover_notes" name="flight_details.layover_notes" class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Layover / connection notes" />
                                                <InputError :message="editError('flight_details.layover_notes')" />
                                                <div class="grid gap-2 min-[460px]:grid-cols-2">
                                                    <div>
                                                        <Input class="travel-touch" v-model="editData.flight_details.online_check_in_opens" name="flight_details.online_check_in_opens" maxlength="80" placeholder="Online check-in opens" />
                                                        <InputError :message="editError('flight_details.online_check_in_opens')" />
                                                    </div>
                                                    <div>
                                                        <Input class="travel-touch" v-model="editData.flight_details.boarding_closes" name="flight_details.boarding_closes" maxlength="80" placeholder="Boarding closes" />
                                                        <InputError :message="editError('flight_details.boarding_closes')" />
                                                    </div>
                                                </div>
                                            </fieldset>

                                            <fieldset class="grid gap-2">
                                                <legend class="text-xs font-medium uppercase text-muted-foreground">Notes</legend>
                                                <textarea v-model="editData.flight_details.notes" name="flight_details.notes" class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Meal preferences, frequent flyer numbers, lounge access, or other flight notes" />
                                                <InputError :message="editError('flight_details.notes')" />
                                            </fieldset>
                                        </div>
                                    </details>
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
                                        <template v-if="reservation.flight_details">
                                            <p v-if="flightDetailsSummary(reservation.flight_details)" class="mt-2 text-sm text-muted-foreground sm:truncate" :title="flightDetailsSummary(reservation.flight_details) ?? undefined">
                                                <Plane class="mr-1 inline h-3.5 w-3.5 -translate-y-0.5" />
                                                {{ flightDetailsSummary(reservation.flight_details) }}
                                            </p>
                                            <button v-if="hasExpandableFlightDetails(reservation.flight_details)" type="button" class="mt-1 text-xs font-medium text-primary underline underline-offset-2" @click="toggleFlightDetails(reservation.id)">
                                                {{ isFlightDetailsExpanded(reservation.id) ? 'Hide full details' : 'Show full details' }}
                                            </button>
                                            <div v-if="isFlightDetailsExpanded(reservation.id)" class="mt-3 grid gap-3 rounded-md border border-border p-3 text-sm">
                                                <div v-if="reservation.flight_details.cabin_class || reservation.flight_details.currency">
                                                    <div class="text-xs font-medium uppercase text-muted-foreground">Cabin &amp; pricing</div>
                                                    <div class="mt-1">
                                                        <span v-if="reservation.flight_details.cabin_class">{{ cabinLabel(reservation.flight_details.cabin_class) }}</span>
                                                        <span v-if="reservation.flight_details.currency" class="text-muted-foreground"> · prices in {{ reservation.flight_details.currency }}</span>
                                                    </div>
                                                </div>

                                                <div v-if="hasAnyBaggageInfo(reservation.flight_details)" class="space-y-2">
                                                    <div class="text-xs font-medium uppercase text-muted-foreground">Baggage allowance</div>
                                                    <div v-if="reservation.flight_details.carry_on_size || reservation.flight_details.carry_on_weight || reservation.flight_details.carry_on_fee">
                                                        <div class="text-xs font-medium">Carry-on</div>
                                                        <div class="text-muted-foreground">{{ [reservation.flight_details.carry_on_size, reservation.flight_details.carry_on_weight, formatPrice(reservation.flight_details.carry_on_fee, reservation.flight_details.currency)].filter(Boolean).join(' · ') }}</div>
                                                    </div>
                                                    <div v-if="reservation.flight_details.personal_item_size || reservation.flight_details.personal_item_weight || reservation.flight_details.personal_item_fee">
                                                        <div class="text-xs font-medium">Personal item / extra carry</div>
                                                        <div class="text-muted-foreground">{{ [reservation.flight_details.personal_item_size, reservation.flight_details.personal_item_weight, formatPrice(reservation.flight_details.personal_item_fee, reservation.flight_details.currency)].filter(Boolean).join(' · ') }}</div>
                                                    </div>
                                                    <div v-if="reservation.flight_details.checked_bag_size || reservation.flight_details.checked_bag_weight || reservation.flight_details.checked_bag_fee">
                                                        <div class="text-xs font-medium">Checked bag</div>
                                                        <div class="text-muted-foreground">{{ [reservation.flight_details.checked_bag_size, reservation.flight_details.checked_bag_weight, formatPrice(reservation.flight_details.checked_bag_fee, reservation.flight_details.currency)].filter(Boolean).join(' · ') }}</div>
                                                    </div>
                                                    <p v-if="reservation.flight_details.additional_checked_bag_fee || reservation.flight_details.additional_checked_bag_allowance">
                                                        Additional checked bags:
                                                        <template v-if="reservation.flight_details.additional_checked_bag_fee">{{ formatPrice(reservation.flight_details.additional_checked_bag_fee, reservation.flight_details.currency) }} each</template>
                                                        <template v-if="reservation.flight_details.additional_checked_bag_allowance"> · {{ reservation.flight_details.additional_checked_bag_allowance }}</template>
                                                    </p>
                                                </div>

                                                <div v-if="reservation.flight_details.visa_requirement || reservation.flight_details.passport_validity_rule" class="space-y-2">
                                                    <div class="text-xs font-medium uppercase text-muted-foreground">Travel documents</div>
                                                    <p v-if="reservation.flight_details.visa_requirement" class="rounded-md bg-amber-50 p-2 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"><strong class="font-medium">Visa:</strong> {{ reservation.flight_details.visa_requirement }}</p>
                                                    <p v-if="reservation.flight_details.passport_validity_rule" class="rounded-md bg-amber-50 p-2 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"><strong class="font-medium">Passport:</strong> {{ reservation.flight_details.passport_validity_rule }}</p>
                                                </div>

                                                <div v-if="reservation.flight_details.layover_notes || reservation.flight_details.online_check_in_opens || reservation.flight_details.boarding_closes" class="space-y-2">
                                                    <div class="text-xs font-medium uppercase text-muted-foreground">Connection &amp; check-in</div>
                                                    <p v-if="reservation.flight_details.layover_notes" class="whitespace-pre-line rounded-md bg-muted p-2 text-sm text-muted-foreground">{{ reservation.flight_details.layover_notes }}</p>
                                                    <div v-if="reservation.flight_details.online_check_in_opens || reservation.flight_details.boarding_closes" class="grid gap-2 min-[460px]:grid-cols-2">
                                                        <div v-if="reservation.flight_details.online_check_in_opens" class="rounded-md border border-border p-2">
                                                            <div class="text-xs font-medium uppercase text-muted-foreground">Check-in opens</div>
                                                            <div>{{ reservation.flight_details.online_check_in_opens }}</div>
                                                        </div>
                                                        <div v-if="reservation.flight_details.boarding_closes" class="rounded-md border border-border p-2">
                                                            <div class="text-xs font-medium uppercase text-muted-foreground">Boarding closes</div>
                                                            <div>{{ reservation.flight_details.boarding_closes }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-if="reservation.flight_details.notes" class="space-y-1">
                                                    <div class="text-xs font-medium uppercase text-muted-foreground">Flight notes</div>
                                                    <p class="rounded-md bg-muted p-2 text-muted-foreground">{{ reservation.flight_details.notes }}</p>
                                                </div>
                                            </div>
                                        </template>
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
                                                <div v-if="trip.can_edit" class="space-y-3 rounded-md bg-muted/40 p-3">
                                                    <label :for="`reservation-attachment-input-${reservation.id}`" class="travel-touch inline-flex cursor-pointer items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90">
                                                        <Upload class="h-4 w-4" />
                                                        Choose files
                                                    </label>
                                                    <input :id="`reservation-attachment-input-${reservation.id}`" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" multiple class="sr-only" @change="stageReservationAttachment(reservation.id, $event)" />
                                                    <ul v-if="attachmentForm.reservation_id === reservation.id && attachmentForm.files.length" class="space-y-1">
                                                        <li v-for="(file, index) in attachmentForm.files" :key="`${file.name}-${index}`" class="flex items-center gap-2 rounded-md bg-background px-2 py-1 text-xs">
                                                            <span class="min-w-0 flex-1 truncate" :title="file.name">{{ file.name }}</span>
                                                            <span class="shrink-0 text-muted-foreground">{{ formatBytes(file.size) }}</span>
                                                            <button type="button" class="travel-touch shrink-0 text-muted-foreground hover:text-destructive" :aria-label="`Remove ${file.name}`" @click="removeStagedAttachment(index)">
                                                                <X class="h-4 w-4" />
                                                            </button>
                                                        </li>
                                                    </ul>
                                                    <form v-if="attachmentForm.reservation_id === reservation.id && attachmentForm.files.length" class="grid gap-2" @submit.prevent="submitReservationAttachment">
                                                        <Input class="travel-touch" v-model="attachmentForm.title_prefix" placeholder="Title prefix (optional)" />
                                                        <Button type="submit" size="sm" class="travel-button-primary" :disabled="attachmentForm.processing">
                                                            Upload {{ attachmentForm.files.length }} file{{ attachmentForm.files.length === 1 ? '' : 's' }} to reservation
                                                        </Button>
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
                                                flight_details: reservation.flight_details ?? blankFlightDetails(trip.suggested_currency),
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
                            <FormErrorSummary :errors="reservationForm.errors" />
                            <select v-model="reservationForm.type" name="type" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="flight">Flight</option>
                                <option value="lodging">Hotel / lodging</option>
                                <option value="transport">Ground transport</option>
                                <option value="activity">Activity</option>
                                <option value="dining">Dining</option>
                                <option value="custom">Custom</option>
                            </select>
                            <InputError :message="reservationForm.errors.type" />
                            <Input class="travel-touch" v-model="reservationForm.title" name="title" placeholder="Reservation title" />
                            <InputError :message="reservationForm.errors.title" />
                            <Input class="travel-touch" v-model="reservationForm.provider_name" name="provider_name" placeholder="Provider" />
                            <InputError :message="reservationForm.errors.provider_name" />
                            <Input class="travel-touch" v-model="reservationForm.booking_reference" name="booking_reference" placeholder="Confirmation number" />
                            <InputError :message="reservationForm.errors.booking_reference" />
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <div>
                                    <Input class="travel-touch" v-model="reservationForm.starts_at" name="starts_at" type="datetime-local" />
                                    <InputError :message="reservationForm.errors.starts_at" />
                                </div>
                                <div>
                                    <Input class="travel-touch" v-model="reservationForm.ends_at" name="ends_at" type="datetime-local" />
                                    <InputError :message="reservationForm.errors.ends_at" />
                                </div>
                            </div>
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <div>
                                    <TimezonePicker v-model="reservationForm.starts_timezone" :timezones="timezones" label="Start timezone" error-target="starts_timezone" placeholder="Start timezone" />
                                    <InputError :message="reservationForm.errors.starts_timezone" />
                                </div>
                                <div>
                                    <TimezonePicker v-if="!reservationTimezonesLinked" v-model="reservationForm.ends_timezone" :timezones="timezones" label="End timezone" error-target="ends_timezone" placeholder="End timezone" />
                                    <div v-else class="travel-touch flex items-center rounded-md border border-input px-3 text-sm text-muted-foreground" data-error-target="ends_timezone">Same as start: {{ reservationForm.starts_timezone }}</div>
                                    <InputError :message="reservationForm.errors.ends_timezone" />
                                </div>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                <input v-model="reservationTimezonesLinked" type="checkbox" class="rounded border-input" />
                                Same end timezone as start
                            </label>
                            <template v-if="reservationForm.type === 'flight'">
                                <div class="grid gap-3 min-[460px]:grid-cols-2">
                                    <div>
                                        <Input class="travel-touch" v-model="reservationForm.airline" name="airline" placeholder="Airline" />
                                        <InputError :message="reservationForm.errors.airline" />
                                    </div>
                                    <div>
                                        <Input class="travel-touch" v-model="reservationForm.flight_number" name="flight_number" placeholder="Flight #" />
                                        <InputError :message="reservationForm.errors.flight_number" />
                                    </div>
                                </div>
                                <div class="grid gap-3 min-[460px]:grid-cols-2">
                                    <div>
                                        <Input class="travel-touch" v-model="reservationForm.departure_airport" name="departure_airport" placeholder="From airport" />
                                        <InputError :message="reservationForm.errors.departure_airport" />
                                    </div>
                                    <div>
                                        <Input class="travel-touch" v-model="reservationForm.arrival_airport" name="arrival_airport" placeholder="To airport" />
                                        <InputError :message="reservationForm.errors.arrival_airport" />
                                    </div>
                                </div>
                            </template>
                            <template v-if="reservationForm.type === 'lodging'">
                                <Input class="travel-touch" v-model="reservationForm.property_name" name="property_name" placeholder="Property name" />
                                <InputError :message="reservationForm.errors.property_name" />
                                <Input class="travel-touch" v-model="reservationForm.room_type" name="room_type" placeholder="Room type" />
                                <InputError :message="reservationForm.errors.room_type" />
                            </template>
                            <Input class="travel-touch" v-model="reservationForm.location_name" name="location_name" placeholder="Location" />
                            <InputError :message="reservationForm.errors.location_name" />
                            <Input class="travel-touch" v-model="reservationForm.address" name="address" placeholder="Address" />
                            <InputError :message="reservationForm.errors.address" />
                            <textarea v-model="reservationForm.notes" name="notes" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Notes" />
                            <InputError :message="reservationForm.errors.notes" />
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

            <section v-if="activePanel === 'details'" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Trip Details</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-3" @submit.prevent="submitTripDetails">
                            <Input v-model="tripForm.name" class="travel-touch" placeholder="Trip name" :disabled="!trip.can_edit" />
                            <InputError :message="tripForm.errors.name" />
                            <Input v-model="tripForm.destination" class="travel-touch" placeholder="Destination" :disabled="!trip.can_edit" />
                            <InputError :message="tripForm.errors.destination" />
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <Input v-model="tripForm.starts_on" class="travel-touch" type="date" :disabled="!trip.can_edit" />
                                <Input v-model="tripForm.ends_on" class="travel-touch" type="date" :disabled="!trip.can_edit" />
                            </div>
                            <InputError :message="tripForm.errors.starts_on || tripForm.errors.ends_on" />
                            <div class="grid gap-3 min-[460px]:grid-cols-2">
                                <TimezonePicker v-model="tripForm.destination_timezone" :timezones="timezones" placeholder="Destination timezone" />
                                <TimezonePicker v-model="tripForm.home_timezone" :timezones="timezones" placeholder="Home timezone" />
                            </div>
                            <InputError :message="tripForm.errors.destination_timezone || tripForm.errors.home_timezone" />
                            <select v-model="tripForm.status" class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm" :disabled="!trip.can_edit">
                                <option value="draft">Draft</option>
                                <option value="planned">Planned</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="archived">Archived</option>
                            </select>
                            <textarea v-model="tripForm.summary" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Summary" :disabled="!trip.can_edit" />
                            <Button type="submit" class="travel-button-primary" :disabled="!trip.can_edit || tripForm.processing">Save trip details</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card class="travel-panel">
                    <CardHeader><CardTitle class="text-base">Timezone Frame</CardTitle></CardHeader>
                    <CardContent class="space-y-3 text-sm text-muted-foreground">
                        <p>Destination dates use {{ effectiveDestinationTimezone }} ({{ timezoneLabel(effectiveDestinationTimezone) }}).</p>
                        <p>Home context uses {{ effectiveHomeTimezone }} ({{ timezoneLabel(effectiveHomeTimezone) }}).</p>
                    </CardContent>
                </Card>
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
    </div>
</template>
