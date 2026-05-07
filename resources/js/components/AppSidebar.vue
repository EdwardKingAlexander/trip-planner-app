<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
import { globalNavItems } from '@/lib/navigation';
import { index as tripsIndex } from '@/routes/trips';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [];
const { isMobile, setOpenMobile } = useSidebar();

const closeMobileNavigation = () => {
    if (isMobile.value) {
        setOpenMobile(false);
    }
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-border bg-muted dark:border-border dark:bg-sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="min-h-12 rounded-lg text-primary hover:bg-accent dark:text-primary dark:hover:bg-accent" as-child>
                        <Link :href="tripsIndex()" @click="closeMobileNavigation">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="globalNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
