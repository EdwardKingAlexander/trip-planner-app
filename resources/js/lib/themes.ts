import type { Theme, ThemeDefinition } from '@/types';

export const DEFAULT_THEME: Theme = 'coastal';

export const THEMES = [
    {
        slug: 'coastal',
        name: 'Coastal',
        description: 'Teal water, pale sand, and coral travel markers.',
        previewSwatches: ['hsl(184 79% 28%)', 'hsl(173 58% 39%)', 'hsl(27 87% 67%)'],
    },
    {
        slug: 'sunset',
        name: 'Sunset',
        description: 'Warm coral, peach light, and a plum night mode.',
        previewSwatches: ['hsl(12 76% 50%)', 'hsl(28 95% 78%)', 'hsl(285 25% 24%)'],
    },
    {
        slug: 'forest',
        name: 'Forest',
        description: 'Evergreen routes, moss accents, and paper neutrals.',
        previewSwatches: ['hsl(152 48% 26%)', 'hsl(142 38% 65%)', 'hsl(35 28% 36%)'],
    },
    {
        slug: 'midnight',
        name: 'Midnight',
        description: 'Indigo structure with electric violet and cyan cues.',
        previewSwatches: ['hsl(230 65% 35%)', 'hsl(258 80% 72%)', 'hsl(190 80% 55%)'],
    },
    {
        slug: 'sandstone',
        name: 'Sandstone',
        description: 'Terracotta trails, sage rest stops, and warm stone.',
        previewSwatches: ['hsl(16 55% 42%)', 'hsl(90 18% 58%)', 'hsl(36 55% 72%)'],
    },
] as const satisfies readonly ThemeDefinition[];

const themeSlugs = new Set<Theme>(THEMES.map((theme) => theme.slug));

export function isTheme(value: unknown): value is Theme {
    return typeof value === 'string' && themeSlugs.has(value as Theme);
}
