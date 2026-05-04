<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    FileText,
    ListChecks,
    Luggage,
    Plane,
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
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Trips',
        href: '/trips',
        icon: Luggage,
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
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/trips">
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
