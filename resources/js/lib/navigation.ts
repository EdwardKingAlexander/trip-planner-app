import { Bell, CalendarDays, Luggage, Search, Timer } from 'lucide-vue-next';
import { index as calendarIndex } from '@/routes/calendar';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as remindersIndex } from '@/routes/reminders';
import { index as tripsIndex, search as tripsSearch } from '@/routes/trips';
import type { NavItem } from '@/types';

export const globalNavItems: NavItem[] = [
    {
        title: 'Trips',
        href: tripsIndex(),
        icon: Luggage,
        matchMode: 'prefix',
        excludePrefixes: [tripsSearch.url()],
    },
    {
        title: 'Search',
        href: tripsSearch(),
        icon: Search,
        matchMode: 'exact',
    },
    {
        title: 'Calendar',
        href: calendarIndex(),
        icon: CalendarDays,
        matchMode: 'exact',
    },
    {
        title: 'Reminders',
        href: remindersIndex(),
        icon: Timer,
        matchMode: 'exact',
    },
    {
        title: 'Notifications',
        href: notificationsIndex(),
        icon: Bell,
        matchMode: 'exact',
    },
];

export const navigationContract = globalNavItems.map((item) => ({
    title: item.title,
    href: typeof item.href === 'string' ? item.href : item.href.url,
    matchMode: item.matchMode ?? 'exact',
    excludePrefixes: item.excludePrefixes ?? [],
}));
