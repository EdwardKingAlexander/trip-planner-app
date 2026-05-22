<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Clock, LogOut, Settings } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import TimezonePicker from '@/components/TimezonePicker.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

const props = defineProps<Props>();
defineEmits<{
    navigate: [];
}>();

const page = usePage();
const timezonePreference = computed(() => page.props.auth.timezone);
const timezones = computed(() => (Array.isArray(page.props.timezones) ? page.props.timezones as string[] : []));
const selectedTimezone = ref(timezonePreference.value.value);

watch(() => timezonePreference.value.value, (timezone) => {
    selectedTimezone.value = timezone;
});

function detectionStorageKey(timezone: string): string {
    return `timezone-detected:${props.user.id}:${timezone}`;
}

function updateTimezone(timezone: string, detected = false): void {
    if (!timezones.value.includes(timezone)) {
        return;
    }

    if (timezone === timezonePreference.value.value) {
        if (detected) {
            localStorage.setItem(detectionStorageKey(timezone), '1');
        }

        return;
    }

    router.patch('/settings/timezone', { timezone }, {
        preserveScroll: true,
        onSuccess: () => {
            if (detected) {
                localStorage.setItem(detectionStorageKey(timezone), '1');
            }
        },
    });
}

function handleTimezoneChange(timezone: string | null): void {
    if (timezone === null) {
        selectedTimezone.value = timezonePreference.value.value;

        return;
    }

    selectedTimezone.value = timezone;
    updateTimezone(timezone);
}

onMounted(() => {
    if (!timezonePreference.value.is_default) {
        return;
    }

    const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (!detectedTimezone || !timezones.value.includes(detectedTimezone)) {
        return;
    }

    if (localStorage.getItem(detectionStorageKey(detectedTimezone)) === '1') {
        return;
    }

    updateTimezone(detectedTimezone, true);
});
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <div class="space-y-2 px-2 py-2">
            <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                <Clock class="h-3.5 w-3.5" />
                Timezone
            </div>
            <TimezonePicker
                :model-value="selectedTimezone"
                :timezones="timezones"
                class="h-9 w-full"
                placeholder="Select timezone"
                @update:model-value="handleTimezoneChange"
            />
            <p class="truncate text-xs text-muted-foreground">{{ selectedTimezone }}</p>
        </div>
        <DropdownMenuSeparator />
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch @click="$emit('navigate')">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="() => { $emit('navigate'); handleLogout(); }"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
