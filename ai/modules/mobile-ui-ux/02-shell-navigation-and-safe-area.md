# Phase 02 - Shell, Navigation, And Safe-Area

## Goal

Stabilize the chrome around every page so subsequent phases are not chasing layout shifts. Respect safe-area insets (notch, home indicator), pin the sticky header at the right height, polish the offcanvas mobile sheet to feel native, and give every page a consistent way to receive a top progress bar for Inertia visits.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01 (audit findings keyed `category: shell | nav | safe-area`)
- Blocker: none

## Audit Inputs

- `resources/views/app.blade.php` (or wherever the root HTML lives) — `<meta name="viewport">` tag.
- `resources/css/app.css` — Tailwind config; current safe-area utility usage (likely none).
- `resources/js/components/AppHeader.vue` — sticky header height + spacing.
- `resources/js/components/AppSidebar.vue` and the offcanvas sheet markup — the mobile menu trigger and behavior.
- `resources/js/layouts/app/AppSidebarLayout.vue` — top-level layout that owns sticky-header padding.
- `resources/js/components/NotificationBell.vue` — dropdown vs sheet on mobile.
- `app.config.js` / `tailwind.config.js` — extend with `safe-area-inset-*` utilities if not present.
- All audit findings tagged `category: shell | nav | safe-area | feedback (toasts)`.

## Strategy

Five small, surgical changes. None of them rewrite a component — each is a CSS or markup tweak applied at the layout layer.

### 1. Viewport meta + tap-zoom

Confirm the root HTML has:

```html
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
```

`viewport-fit=cover` is the unlock — without it the page does not extend under the notch / home indicator and the safe-area-inset env() values report zero. Do **not** add `user-scalable=no` — keep zoom enabled for accessibility; the double-tap-to-zoom annoyance is solved by `touch-action: manipulation` on tappable elements (Tailwind: `touch-manipulation` utility).

### 2. Safe-area utility classes

Add to `resources/css/app.css`:

```css
@layer utilities {
    .pt-safe { padding-top: max(env(safe-area-inset-top), 0px); }
    .pb-safe { padding-bottom: max(env(safe-area-inset-bottom), 0px); }
    .pl-safe { padding-left: max(env(safe-area-inset-left), 0px); }
    .pr-safe { padding-right: max(env(safe-area-inset-right), 0px); }
    .pb-safe-or-4 { padding-bottom: max(env(safe-area-inset-bottom), theme('spacing.4')); }
    .pt-safe-or-3 { padding-top: max(env(safe-area-inset-top), theme('spacing.3')); }
}
```

Apply per audit findings:

- `AppSidebarLayout.vue` outer wrapper gains `pl-safe pr-safe`.
- `AppHeader.vue` sticky bar gains `pt-safe-or-3`.
- The offcanvas mobile sheet's content gains `pt-safe pb-safe-or-4`.
- The lightbox modal's close button container gains `pt-safe-or-3 pr-safe`.
- The new bottom-anchored toast region (introduced in Phase 05) uses `pb-safe-or-4`.

`touch-manipulation` utility is added to every interactive element wrapper class (`travel-touch`, `travel-button-primary`).

### 3. Sticky header height + scroll-margin

Set a consistent header height variable so anchored deep links (from `notification-deep-links`) scroll to a position that isn't hidden under the header.

```css
:root {
    --header-height: 56px;
}

@media (min-width: 640px) {
    :root {
        --header-height: 64px;
    }
}

[id^="reservation-"],
[id^="packing-item-"],
[id^="document-"],
[id^="task-"],
[id^="reminder-"],
[id^="cost-"],
[id^="itinerary-item-"],
[id^="collaborator-"] {
    scroll-margin-top: calc(var(--header-height) + 12px);
}
```

`AppHeader.vue` matches `--header-height` exactly with a Tailwind arbitrary class (`h-[var(--header-height)]`). The `--header-height` value is *exclusive* of the safe-area top inset; the inset is added on top via `pt-safe-or-3`.

### 4. Offcanvas sheet polish

The offcanvas sheet (post-`scroll-issue` Phase 03) opens cleanly but on iOS Safari it can:

- Allow body scroll behind it (rubber-banding).
- Skip a focus trap (Tab cycles back to the page underneath).
- Animate from the wrong edge on right-to-left environments.

Apply:

- `body { overflow: hidden; touch-action: none; }` while the sheet is open. Cleanest implementation: the existing `<Sheet>` component (if shadcn-vue) already does this; verify and patch if missing.
- Confirm focus trap via `aria-modal="true"` + `role="dialog"` + Vue's `@keydown.tab.prevent` cycling logic in the sheet.
- The trigger button (`AppSidebarHeader.vue` hamburger) gets `aria-expanded` + `aria-controls`.

### 5. Inertia top progress bar

If `@inertiajs/vue3` doesn't already have the progress plugin enabled, enable it in `resources/js/app.ts`:

```ts
import { progress } from '@inertiajs/vue3';

createInertiaApp({
    // ...
    progress: {
        color: 'var(--primary)',
        delay: 200,        // don't flash for fast visits
        includeCSS: true,
        showSpinner: false,
    },
});
```

The thin bar appears at the top of the viewport. With `viewport-fit=cover` + safe-area-aware containers, it stays at the visual top.

If the progress plugin is already enabled, just verify the color uses the active theme's `--primary` and the bar sits *above* the safe-area inset (not visually under the notch).

### 6. Toast / flash positioning

Move the existing flash/toast renderer (look for the component listening to `usePage().props.flash`) to:

- Top-right on `≥sm` (current behavior).
- Bottom-center on `<sm`, anchored above the safe area: `class="sm:fixed sm:right-4 sm:top-4 fixed inset-x-4 bottom-0 pb-safe-or-4 z-50"`.
- Single dismissible button with a 44 × 44 hit area.

If there is no existing toast renderer (the app may rely on flash messages rendered inline at the top of the page), this is the place to add a minimal one — but only after confirming with the audit.

## Planned Changes Summary

- `resources/views/app.blade.php` (or layout source) — add `viewport-fit=cover` to the viewport meta.
- `resources/css/app.css` — add safe-area utilities + scroll-margin rules + `--header-height` variable.
- `resources/js/components/AppHeader.vue` — sticky header height + safe-area top padding.
- `resources/js/layouts/app/AppSidebarLayout.vue` — safe-area left/right padding on the outer wrapper.
- `resources/js/components/AppSidebarHeader.vue` — `aria-expanded` / `aria-controls` on the trigger.
- `resources/js/components/ui/sheet/Sheet*.vue` (if shadcn-vue files exist locally) — confirm focus trap + body scroll lock.
- `resources/js/app.ts` — enable Inertia progress plugin if not present.
- New / existing toast renderer — responsive position.

## State Management

- `--header-height` is a CSS variable, not a JS ref. Single source of truth in `resources/css/app.css`.
- Sheet open/closed state stays where it lives today (the existing `Sheet` component's internal ref).
- Progress bar visibility is owned by Inertia router events — no manual state.
- Toast state stays in the existing flash-message wiring — only visual position changes.

## Tests

This phase is shell-level CSS + tiny JS. Rely on:

- Phase 06 manual matrix on real devices (the only authoritative test for safe-area).
- `npm run lint:check`, `npm run types:check`, `npm run build` as the required gates.
- One Pest browser smoke (matching the verification approach of neighbouring modules):
  - Visit `/trips` on a 390×844 viewport.
  - Assert the header has the safe-area top padding class.
  - Assert the mobile sheet trigger is keyboard-reachable and toggles `aria-expanded`.
  - Assert a forced flash message renders at the bottom of the viewport on `<sm` and the top-right at `≥sm`.

## Acceptance Criteria

- `<meta name="viewport">` includes `viewport-fit=cover`.
- `pt-safe`, `pb-safe`, `pl-safe`, `pr-safe`, `pb-safe-or-4`, `pt-safe-or-3` utilities exist and are applied where called for.
- The sticky header height is exactly `--header-height` plus the top safe-area inset; deep-link anchors land below it (not under it).
- The offcanvas mobile sheet locks body scroll, traps focus, and announces `aria-expanded` on its trigger.
- The Inertia top progress bar appears for visits that take >200 ms; color tracks the active theme's `--primary`.
- Flash / toast messages render at the bottom on `<sm` and top-right at `≥sm`, never under the safe area, dismissible from a 44 px hit area.
- All audit findings tagged `shell | nav | safe-area` are addressed.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Building the full-screen edit Sheet pattern (Phase 03).
- Input picker fixes (Phase 04).
- Row swipe-to-delete and skeletons (Phase 05).
- Replacing the existing `Sheet` component implementation.
- Adding a global toast queue / toast manager / `useToast()` hook (the existing flash-message flow is enough; revisit only if Phase 05 needs queueing).
- Reorganizing the existing nav IA (already shipped via `navigation-uplift`).
