# Phase 01 - Theme Foundation And Registry

## Goal

Establish the theme contract and a single source of truth for the available themes, then plug it into the existing appearance composable so the rest of the work has stable shapes to consume.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: none
- Blocker: none

## Planned Changes

### `resources/js/types/ui.ts`

- Add `Theme` union: `'coastal' | 'sunset' | 'forest' | 'midnight' | 'sandstone'`.
- Add `ThemeDefinition` shape: `{ slug: Theme; name: string; description: string; previewSwatches: [string, string, string]; }`.
- Re-export `Theme` from `resources/js/types/index.ts` alongside `Appearance`.

### `resources/js/lib/themes.ts` (new)

- Export `THEMES: readonly ThemeDefinition[]` listing the five themes in master-plan order.
- Export `DEFAULT_THEME: Theme = 'coastal'` to preserve the current visual.
- Export `isTheme(value: unknown): value is Theme` guard for cookie/localStorage hydration.
- No CSS lives here — only metadata. Token CSS belongs to Phase 03.

### `resources/js/composables/useTheme.ts` (new)

- Mirror the structure of `useAppearance.ts`:
  - Module-scoped `ref<Theme>` initialized to `DEFAULT_THEME`.
  - `initializeTheme()` reads cookie/localStorage and applies the `data-theme` attribute on `document.documentElement` before mount.
  - `useTheme()` returns `{ theme, themes, updateTheme }`.
  - `updateTheme(value)` updates the ref, sets `data-theme` on `<html>`, writes localStorage key `theme`, and writes cookie `theme` (`max-age=365d`, `SameSite=Lax`, `path=/`).
- Do not couple to `useAppearance` — the two compose by both being applied to `<html>` (`data-theme` + `dark` class).

### `resources/js/app.ts`

- Call `initializeTheme()` alongside the existing `initializeAppearance()` call so both render before Vue mounts. Order: theme first, then appearance, so appearance overrides any theme-related defaults if needed.

### `resources/js/ssr.ts`

- Mirror the same `initializeTheme()` call in the SSR entry. The cookie value is the source of truth here (Phase 02 wires the cookie through to SSR props).

## State Management

- Single in-memory ref in `useTheme.ts` is the runtime owner.
- DOM `data-theme="<slug>"` on `<html>` is the rendering owner — every CSS variable block in Phase 03 selects on it.
- localStorage `theme` is the client persistence owner.
- Cookie `theme` is the SSR/initial-paint owner (Phase 02 reads it server-side).
- DB column on `users` is the cross-device owner (Phase 02).

Precedence on hydration: server-rendered cookie value → localStorage → `DEFAULT_THEME`.

## Acceptance Criteria

- `Theme` type, `THEMES` list, `isTheme` guard, and `useTheme` composable all exist and are exported.
- `data-theme` attribute is set on `<html>` before Vue mounts, with no console errors.
- Calling `updateTheme('sunset')` from the browser console flips the attribute and writes both localStorage and cookie.
- `npm run types:check` and `npm run lint:check` pass.

## Out Of Scope

- Any CSS that defines what each theme looks like (Phase 03).
- The settings UI to change the theme (Phase 04).
- Persisting the theme to the user record (Phase 02).
