# Themes Plan Master Plan

## Goal

Allow the user to choose between five premade visual themes for the authenticated app. Each theme defines a coordinated set of color tokens for light and dark mode, persists across reloads (localStorage + cookie + DB), and ships through the existing appearance settings surface.

## Status

- Status: `planned`
- State file: `ai/state/themes-plan.json`
- Last updated: 2026-05-06

## Background

The app already has a light/dark appearance switch wired through `resources/js/composables/useAppearance.ts` and a single `:root` / `.dark` token block in `resources/css/app.css`. Theme tokens (background, foreground, primary, sidebar, chart, etc.) are exposed to Tailwind v4 through `@theme inline` and consumed by the shadcn/Reka UI components in `resources/js/components/ui/**`. The legacy `travel-*` utility classes in `app.css` use hard-coded coastal hex values rather than tokens, which is why the existing default look ("Coastal") must be preserved as one of the five themes.

## The Five Themes

| Slug | Name | Identity | Light Anchor | Dark Anchor |
| --- | --- | --- | --- | --- |
| `coastal` | Coastal | Existing default — teal, sand, sunset coral | teal `184 79% 28%` | aqua `174 62% 72%` |
| `sunset` | Sunset | Warm oranges, corals, plum, peach | coral `12 76% 50%` | peach `16 80% 70%` |
| `forest` | Forest | Deep evergreen, moss, bark, cream | forest `152 48% 26%` | sage `142 38% 65%` |
| `midnight` | Midnight | Indigo, slate, electric violet accent | indigo `230 65% 35%` | violet `258 80% 72%` |
| `sandstone` | Sandstone | Desert terracotta, sage, warm neutral | terracotta `16 55% 42%` | sand `28 60% 70%` |

Theme + appearance compose: every theme has a light variant and a dark variant. The user picks a theme and a light/dark/system preference independently.

## Phases

1. [Theme Foundation And Registry](01-theme-foundation-and-registry.md)
2. [Theme Persistence And SSR](02-theme-persistence-and-ssr.md)
3. [Five Premade Themes](03-five-premade-themes.md)
4. [Theme Switcher UI](04-theme-switcher-ui.md)
5. [Verification And Release](05-verification-and-release.md)

## Implementation Order

Build phases in order. Foundation defines the contract that the persistence, theme CSS, and UI phases depend on; verification is last because it exercises the complete loop on every theme.

## Acceptance Criteria

- The user can pick any of the five themes from the appearance settings page and see the change apply immediately without a full reload.
- The chosen theme survives reload, hard-refresh, and SSR (no flash of wrong theme on first paint).
- Theme + appearance (light/dark/system) combine correctly for all five themes.
- Existing UI components (sidebar, cards, dialogs, dropdowns, alerts, charts, badges, buttons, navigation menu, inputs, breadcrumbs) remain legible at WCAG AA contrast in every theme + appearance combination.
- The legacy `travel-*` utility classes in `resources/css/app.css` consume theme tokens instead of the hard-coded coastal hex values, so they restyle with the active theme.
- `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, and `vendor/bin/pint --dirty --format agent` all pass.

## Out Of Scope

- User-defined custom themes or color pickers.
- Per-trip themes.
- Marketing site / unauthenticated landing theming beyond the existing default.
- Animated theme transitions beyond the simple CSS variable swap.
