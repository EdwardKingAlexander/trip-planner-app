<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpenMobile } = useSidebar();

const closeMobileNavigation = () => {
    if (isMobile.value) {
        setOpenMobile(false);
    }
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    class="min-h-11 rounded-lg text-[#315861] data-[active=true]:bg-[#d9f0ef] data-[active=true]:text-[#0f5f68] hover:bg-[#eaf7f8] dark:text-[#d6e6e8] dark:data-[active=true]:bg-[#183640] dark:data-[active=true]:text-[#d6f5f1] dark:hover:bg-[#152c34]"
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href" @click="closeMobileNavigation">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
