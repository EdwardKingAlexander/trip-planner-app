import { router, usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import { update as updateThemePreference } from '@/actions/App/Http/Controllers/Settings/ThemeController';
import { DEFAULT_THEME, isTheme, THEMES } from '@/lib/themes';
import type { Theme, ThemeDefinition } from '@/types';

export type UseThemeReturn = {
    theme: Ref<Theme>;
    themes: readonly ThemeDefinition[];
    updateTheme: (value: Theme) => void;
};

const theme = ref<Theme>(DEFAULT_THEME);

const cookieMaxAge = 365 * 24 * 60 * 60;

function setCookie(value: Theme): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `theme=${value};path=/;max-age=${cookieMaxAge};SameSite=Lax`;
}

function readCookie(): Theme | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const cookieValue = document.cookie
        .split('; ')
        .find((row) => row.startsWith('theme='))
        ?.split('=')[1];

    return isTheme(cookieValue) ? cookieValue : null;
}

function readStoredTheme(): Theme | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const storedTheme = localStorage.getItem('theme');

    return isTheme(storedTheme) ? storedTheme : null;
}

function renderedTheme(): Theme | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const value = document.documentElement.dataset.theme;

    return isTheme(value) ? value : null;
}

function applyTheme(value: Theme): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.theme = value;
}

function persistTheme(value: Theme): void {
    if (typeof window !== 'undefined') {
        localStorage.setItem('theme', value);
    }

    setCookie(value);
}

function sharedTheme(): Theme | null {
    const page = usePage();
    const value = page.props.theme;

    return isTheme(value) ? value : null;
}

function hasAuthenticatedUser(): boolean {
    const page = usePage();

    return Boolean(page.props.auth?.user?.id);
}

export function initializeTheme(): void {
    const resolvedTheme = renderedTheme() ?? readCookie() ?? readStoredTheme() ?? DEFAULT_THEME;

    theme.value = resolvedTheme;
    applyTheme(resolvedTheme);
}

export function useTheme(): UseThemeReturn {
    onMounted(() => {
        const resolvedTheme =
            sharedTheme() ?? renderedTheme() ?? readStoredTheme() ?? readCookie() ?? DEFAULT_THEME;

        if (theme.value !== resolvedTheme) {
            theme.value = resolvedTheme;
            applyTheme(resolvedTheme);
            persistTheme(resolvedTheme);
        }
    });

    function updateTheme(value: Theme): void {
        theme.value = value;
        applyTheme(value);
        persistTheme(value);

        if (hasAuthenticatedUser()) {
            router.patch(
                updateThemePreference.url(),
                { theme: value },
                {
                    preserveScroll: true,
                    preserveState: true,
                },
            );
        }
    }

    return {
        theme,
        themes: THEMES,
        updateTheme,
    };
}
