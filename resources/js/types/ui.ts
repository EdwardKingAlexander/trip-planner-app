export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';
export type Theme = 'coastal' | 'sunset' | 'forest' | 'midnight' | 'sandstone';

export type ThemeDefinition = {
    slug: Theme;
    name: string;
    description: string;
    previewSwatches: [string, string, string];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};
