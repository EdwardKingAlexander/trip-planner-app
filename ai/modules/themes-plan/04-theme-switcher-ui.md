# Phase 04 - Theme Switcher UI

## Goal

Add a theme picker to the appearance settings page so users can change themes through the UI, with previews and immediate application. Sit it next to the existing light/dark/system tabs so the two controls feel like one feature.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01 (composable + registry), Phase 02 (persistence), Phase 03 (theme tokens render correctly)
- Blocker: none

## Planned Changes

### `resources/js/components/ThemeSwatch.vue` (new)

- Props: `theme: ThemeDefinition`, `selected: boolean`.
- Renders a labeled card with three preview swatches (`previewSwatches` from the registry), the theme name, and a one-line description.
- Selected state uses `ring-2 ring-ring` and an aria-checked indicator.
- Pure presentational — no store access, emits `select` on click.
- Min-height matches `travel-touch` (`min-h-11`) for the click target.

### `resources/js/components/ThemePicker.vue` (new)

- Calls `useTheme()` to read current theme + theme list.
- Renders `THEMES` as a responsive grid (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3`) of `ThemeSwatch` cards.
- On select, calls `updateTheme(slug)` from the composable. The composable handles localStorage, cookie, DOM, and the background `router.patch` (added in Phase 02).
- Includes a small `<p>` helper text: "Theme controls colors. Light/dark controls brightness."

### `resources/js/pages/settings/Appearance.vue` (existing — extend, do not duplicate)

- Section header order: theme picker first, then the existing `AppearanceTabs` (light/dark/system), separated by `Separator` from `@/components/ui/separator` (or an `hr` matching local convention).
- Both controls are independent — picking a theme does not change the appearance, and vice versa.
- Page already inherits the settings layout; do not touch the layout shell.

### `resources/js/components/ThemeSwatch.vue` accessibility

- Renders as a `<button type="button" role="radio" :aria-checked="selected">` inside a `role="radiogroup"` provided by `ThemePicker.vue`.
- Keyboard: `Enter` and `Space` select. Arrow keys move between swatches inside the radiogroup.
- Each swatch has an accessible label combining the theme name and a short status ("Active" when selected).

### Tests

#### `tests/Browser/AppearanceThemePickerTest.php` (new, Pest browser)

- Visits `/settings/appearance` as an authenticated user.
- Asserts five swatches are present.
- Clicks each in turn and asserts `<html>` `data-theme` updates.
- Verifies no JS errors are thrown during the interaction (use the smoke-test pattern from `pest-testing` skill).

#### `tests/Feature/Settings/AppearancePageTest.php` (extend if it exists, else new)

- Inertia render assertion includes `theme` in the shared props.
- The page renders the picker component (assert by visible heading text "Theme").

## State Management

- `ThemePicker.vue` is a thin consumer of `useTheme()`. No local state beyond what the composable provides.
- `ThemeSwatch.vue` is stateless; it derives `selected` from a prop comparison.
- Inertia shared `theme` prop hydrates the composable on mount, so the correct swatch is highlighted on first render with no flash.

## Acceptance Criteria

- The appearance settings page shows a 5-swatch theme picker above the existing light/dark/system tabs.
- Clicking a swatch immediately restyles the entire app, persists across reload, and survives logout/login on the same device.
- Keyboard navigation works: tab into the radiogroup, arrow between swatches, space to select.
- The active swatch has a visible focus + selected ring that meets WCAG AA against its swatch background.
- Browser smoke test passes with no JS errors during theme cycling.

## Out Of Scope

- Custom user-defined themes.
- A theme picker outside the settings page (e.g. in the header dropdown).
- Animated swatch transitions beyond a basic CSS hover state.
