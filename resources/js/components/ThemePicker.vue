<script setup lang="ts">
import ThemeSwatch from '@/components/ThemeSwatch.vue';
import { useTheme } from '@/composables/useTheme';
import type { Theme } from '@/types';

const { theme, themes, updateTheme } = useTheme();

function selectTheme(value: Theme): void {
    updateTheme(value);
}

function focusSwatch(currentSlug: Theme, direction: 1 | -1): void {
    const currentIndex = themes.findIndex((candidate) => candidate.slug === currentSlug);
    const nextIndex = (currentIndex + direction + themes.length) % themes.length;
    const nextTheme = themes[nextIndex];

    document
        .querySelector<HTMLButtonElement>(`[data-theme-option="${nextTheme.slug}"]`)
        ?.focus();
}
</script>

<template>
    <section class="space-y-3" aria-labelledby="theme-picker-heading">
        <div class="flex flex-col gap-1">
            <h2 id="theme-picker-heading" class="text-sm font-medium">Theme</h2>
            <p class="text-sm text-muted-foreground">
                Theme controls colors. Light/dark controls brightness.
            </p>
        </div>

        <div role="radiogroup" aria-labelledby="theme-picker-heading" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <ThemeSwatch
                v-for="themeOption in themes"
                :key="themeOption.slug"
                :data-theme-option="themeOption.slug"
                :theme="themeOption"
                :selected="theme === themeOption.slug"
                @select="selectTheme"
                @navigate="focusSwatch(themeOption.slug, $event)"
            />
        </div>
    </section>
</template>
