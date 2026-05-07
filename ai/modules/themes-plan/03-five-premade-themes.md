# Phase 03 - Five Premade Themes

## Goal

Define the actual visual identity of all five themes by writing CSS variable blocks scoped to `[data-theme="<slug>"]` (light) and `[data-theme="<slug>"].dark` (dark), and rewrite the legacy `travel-*` utility classes to consume tokens so they restyle with the active theme.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01 (`data-theme` attribute is set on `<html>`)
- Blocker: none

## Planned Changes

### `resources/css/app.css` — restructure tokens

Current `:root` and `.dark` blocks become the `coastal` theme blocks. Every theme defines the same complete token set so no theme leans on inheritance from `:root`.

Selectors per theme (light first, dark second):

```css
:root,
[data-theme='coastal'] {
    /* coastal light tokens */
}
[data-theme='coastal'].dark {
    /* coastal dark tokens */
}
[data-theme='sunset'] {
    /* sunset light tokens */
}
[data-theme='sunset'].dark {
    /* sunset dark tokens */
}
/* ...forest, midnight, sandstone... */
```

`:root` keeps the coastal light values so unauthenticated/initial-paint pages without `data-theme` still render correctly. `.dark` (legacy) stays as a fallback equal to `[data-theme='coastal'].dark` so existing components without a `data-theme` ancestor do not regress.

### Token palette (per theme, all in HSL like the existing file)

Each theme MUST define every variable already present in the current `:root` block: `background`, `foreground`, `card`, `card-foreground`, `popover`, `popover-foreground`, `primary`, `primary-foreground`, `secondary`, `secondary-foreground`, `muted`, `muted-foreground`, `accent`, `accent-foreground`, `destructive`, `destructive-foreground`, `border`, `input`, `ring`, `chart-1..5`, `sidebar-background`, `sidebar-foreground`, `sidebar-primary`, `sidebar-primary-foreground`, `sidebar-accent`, `sidebar-accent-foreground`, `sidebar-border`, `sidebar-ring`, `sidebar`. `--radius` stays global (not per theme).

#### `coastal` (default — preserve current look)

- Light primary `184 79% 28%`, ring matches primary, charts as today.
- Dark primary `174 62% 72%`, sidebar `0 0% 7%`.
- Pull existing values straight from the current `:root` and `.dark` blocks.

#### `sunset`

- Light: warm cream background `30 50% 97%`, deep coral primary `12 76% 50%`, peach accent `28 95% 88%`, plum destructive stays standard, sandy muted, charts in coral/peach/plum/gold/teal-grey.
- Dark: bruised plum background `285 25% 10%`, peach primary `16 80% 70%`, ember accent `12 50% 22%`, soft sand foreground.

#### `forest`

- Light: paper background `42 22% 97%`, evergreen primary `152 48% 26%`, mossy accent `120 15% 90%`, bark border `35 18% 80%`, charts in moss/sage/bark/cream/rust.
- Dark: pine background `155 25% 7%`, sage primary `142 38% 65%`, conifer accent `155 22% 14%`.

#### `midnight`

- Light: cool white background `220 30% 98%`, indigo primary `230 65% 35%`, lavender accent `240 30% 92%`, slate border, charts in indigo/violet/cyan/magenta/teal.
- Dark: navy background `230 35% 7%`, electric violet primary `258 80% 72%`, deep indigo accent `230 30% 14%`.

#### `sandstone`

- Light: bone background `36 30% 96%`, terracotta primary `16 55% 42%`, sage accent `90 15% 88%`, sand border `35 25% 82%`, charts in clay/sage/sky/dune/sun.
- Dark: dune background `28 18% 8%`, sand primary `28 60% 70%`, ember accent `20 30% 18%`.

Each theme block must hit WCAG AA contrast for `foreground` over `background`, `primary-foreground` over `primary`, and `muted-foreground` over `muted`. Verify with a contrast checker before committing.

### `resources/css/app.css` — `@layer components` cleanup

The legacy `travel-*` utilities currently hard-code coastal hex values:

- `.travel-page` → `bg-background text-foreground` (drop the hex literals).
- `.travel-panel` → `rounded-lg border border-border bg-card shadow-sm`.
- `.travel-hero` → same pattern, using `bg-card`/`border-border`.
- `.travel-muted` → `text-muted-foreground`.
- `.travel-button-primary` → `min-h-11 bg-primary text-primary-foreground hover:bg-primary/90`.
- `.travel-stripe` → keep the literal gradient but wrap in a CSS variable: define `--travel-stripe` per theme so each theme gets its own signature stripe (coastal keeps the existing gradient; each new theme defines a 5-stop gradient drawn from its palette).
- `.travel-touch` stays as-is (no color).

### `resources/js/components/PlaceholderPattern.vue`

- Uses neutral stroke colors today; switch to `stroke-muted-foreground` if it currently hard-codes a value, so previews adapt to the active theme.

## State Management

- No new state. This phase is pure presentation: CSS variable blocks keyed by `data-theme` on `<html>`, plus the existing `dark` class for variant.
- Toggling `data-theme` causes an immediate atomic restyle via the CSS variable cascade — no JS animation, no transition delay.

## Acceptance Criteria

- Setting `document.documentElement.dataset.theme = '<slug>'` for each of the five slugs visibly changes the entire app palette in both light and dark mode.
- Removing `data-theme` falls back to coastal (because `:root` mirrors `[data-theme='coastal']`).
- The legacy `travel-*` utility classes restyle when the theme changes (no remaining hard-coded coastal hex values except inside `--travel-stripe` definitions).
- WCAG AA contrast ratios verified for `foreground/background`, `primary-foreground/primary`, `muted-foreground/muted` on all five themes × light/dark.
- `npm run build` succeeds and the resulting CSS contains all 10 theme blocks (5 themes × light/dark).

## Out Of Scope

- A live preview in the settings UI (Phase 04).
- Animated transitions between themes.
- Per-component theme overrides.
