import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, DeepReadonly } from 'vue';
import { computed, readonly } from 'vue';
import { toUrl } from '@/lib/utils';

export type UseCurrentUrlReturn = {
    currentUrl: DeepReadonly<ComputedRef<string>>;
    isCurrentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith?: boolean,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        excludePrefixes?: string[],
    ) => boolean;
    whenCurrentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
};

export function normalizePath(url: NonNullable<InertiaLinkProps['href']>): string {
    const urlString = toUrl(url);

    if (!urlString.startsWith('http')) {
        return new URL(urlString, 'http://localhost').pathname;
    }

    return new URL(urlString).pathname;
}

export function isUrlActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
    startsWith: boolean = false,
    excludePrefixes: string[] = [],
): boolean {
    const urlToCompare = normalizePath(currentUrl);
    const path = normalizePath(urlToCheck);

    if (excludePrefixes.some((prefix) => urlToCompare.startsWith(prefix))) {
        return false;
    }

    return startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;
}

const page = usePage();
const currentUrlReactive = computed(
    () =>
        new URL(
            page.url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ).pathname,
);

export function useCurrentUrl(): UseCurrentUrlReturn {
    function isCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) {
        try {
            return isUrlActive(urlToCheck, currentUrl ?? currentUrlReactive.value, startsWith);
        } catch {
            return false;
        }
    }

    function isCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        excludePrefixes: string[] = [],
    ) {
        try {
            return isUrlActive(urlToCheck, currentUrl ?? currentUrlReactive.value, true, excludePrefixes);
        } catch {
            return false;
        }
    }

    function whenCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
