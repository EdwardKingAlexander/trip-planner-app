<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    FileText,
    ListChecks,
    Luggage,
    Plane,
    Search,
    Share2,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Trips',
        href: '/trips',
        icon: Luggage,
    },
    {
        title: 'Search',
        href: '/trips/search',
        icon: Search,
    },
    {
        title: 'Calendar',
        href: '/trips',
        icon: CalendarDays,
    },
    {
        title: 'Reservations',
        href: '/trips',
        icon: Plane,
    },
    {
        title: 'Packing',
        href: '/trips',
        icon: ListChecks,
    },
    {
        title: 'Documents',
        href: '/trips',
        icon: FileText,
    },
    {
        title: 'Sharing',
        href: '/trips',
        icon: Share2,
    },
    {
        title: 'Reminders',
        href: '/trips',
        icon: Bell,
    },
];

const footerNavItems: NavItem[] = [];
const { isMobile, setOpenMobile } = useSidebar();

const closeMobileNavigation = () => {
    if (isMobile.value) {
        setOpenMobile(false);
    }
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-[#c8dde0] bg-[#f6fbfb] dark:border-[#25414a] dark:bg-[#0f1d23]">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="min-h-12 rounded-lg text-[#174f57] hover:bg-[#e4f5f6] dark:text-[#d6f5f1] dark:hover:bg-[#183640]" as-child>
                        <Link href="/trips" @click="closeMobileNavigation">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
