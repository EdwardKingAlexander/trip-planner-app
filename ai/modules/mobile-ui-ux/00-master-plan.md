# Mobile UI/UX Master Plan

## Goal

Take the app from "renders without breaking on a phone" (which `responsive-ui-uplift`, `scroll-issue`, and `navigation-uplift` already delivered) to "feels like an app designed for a phone." Specifically: replace inline edit forms that swallow the screen with full-screen sheets, use native input pickers where the OS already does it better, respect safe-area insets, give touch users actual row-level actions, and ship loading/feedback states that don't make a 600 ms request feel like 6 seconds.

## Status

- Status: `planned`
- State file: `ai/state/mobile-ui-ux.json`
- Last updated: 2026-05-14

## Context Of Prior Work

Three earlier modules touched mobile and shipped:

- `ai/modules/responsive-ui-uplift.md` (verified 2026-05-05) — bumped touch targets to ≥44 px, made dense forms 1-column on mobile, switched trip tool nav from horizontal rail to segmented grid, refreshed palette.
- `ai/modules/scroll-issue/` (verified 2026-05-06) — established a single document-level scroll owner on desktop and the offcanvas mobile menu.
- `ai/modules/navigation-uplift/` (verified 2026-05-07) — desktop sidebar and mobile sheet now share an item list with typed routes and accurate active state.

What those modules did **not** address: in-page interaction patterns (forms, modals, lists, lightboxes, drop zones), input-type choices for native OS pickers, safe-area insets, loading/skeleton states, swipe / long-press affordances, on-screen keyboard handling, and the cumulative density of `Trips/Show.vue` — a workspace page that on a phone shows a hero, stats grid, six action buttons, eight panel tabs, and a stacked panel underneath.

## Problem

The app is usable on a phone but operating it feels like operating a desktop UI through a phone-shaped window. Specific patterns that hurt:

- **Inline edit forms inside cards.** Tapping Edit on a packing item, a reservation, or a document opens the form *inside the card*, pushing the rest of the page down. The user types with the keyboard up, can't see the row they're editing, and after Save the page snaps. On desktop this is fine; on a 390 px viewport it's disorienting.
- **Drop zones with "drag and drop here" copy.** The documents-tab upload UI is built for a mouse. The button works on touch but the dashed-border zone, drag-active highlight, and "drag and drop" text are noise.
- **Native date/time inputs typed `datetime-local`** that the user fills with two-finger taps. iOS Safari renders these as a wheel picker only when the input is `date`, `time`, or `datetime-local` *with no value attribute manipulation*; there are subtle browser-specific bugs the existing code may trip.
- **Modals/lightboxes with `inset-0` and no `pb-safe` / `env(safe-area-inset-bottom)`** — close buttons sit under the iPhone notch / home indicator on devices with safe areas.
- **Lists with no row-level touch action.** Packing items, documents, reservations all show a small Edit + Delete button cluster at the right edge. On phone-width those buttons are tightly packed; misclicks on Delete are a real risk. The convention is swipe-to-reveal or long-press menu.
- **Sticky tab nav consuming viewport.** The 6-tab segmented grid plus the sticky header eats ~140 px of vertical space at the top of the trip workspace before any content appears.
- **Form errors out of view.** When the form is long (the new `flight_details` section, the reservation form, the document upload form) and the user submits, validation errors render at the field — but on mobile the field may be 800 px above the submit button. There's no scroll-to-first-error.
- **Loading/skeleton states absent.** The trip detail loads via Inertia; on a slow connection the page hangs blank then blinks in. No skeleton, no progress bar — the user thinks the tap didn't register.
- **Toasts / flash messages near the top.** On mobile, the user's thumb is at the *bottom* of the screen. A success toast at the top is hard to dismiss one-handed.
- **The number-typing virtual keyboard** appears for `type="number"` inputs but lacks the `inputmode` and `pattern` hints that give iOS the cleanest decimal pad.
- **Tap-to-zoom is enabled.** Without `<meta viewport content="..., user-scalable=no">` (or the better `width=device-width, initial-scale=1` only), iOS double-tap zooms — usually unwanted on a workspace app.
- **Long edit forms have no section navigation.** The flight-details `<details>` block + the existing reservation form add up to ~15 fields the user has to scroll through to reach Save.

This module catalogs these and ships a coherent fix.

## Strategy

Six threads, in order:

1. **Audit first, then fix.** Phase 01 is a no-code inventory of every concrete pain point at three real viewport widths (360 px, 390 px, 430 px) plus tablet (820 px) for boundary cases. Phases 02–05 are keyed to audit findings — no fix ships without a documented finding.
2. **Shell + safe-area + sticky behavior.** Phase 02 — the chrome around every page. Safe-area insets, viewport meta, sticky header height, mobile sheet polish, toast/flash positioning.
3. **Trip workspace and full-screen forms.** Phase 03 — the biggest mobile screen. Replace inline edit forms with a full-screen `<Sheet>` on mobile (keep inline on desktop). Add a "jump to section" mini-nav for long forms. Address the cumulative density of `Trips/Show.vue`.
4. **Inputs and OS-native pickers.** Phase 04 — date / time / number / currency / file inputs. Use the right `type`, `inputmode`, `enterkeyhint`, and `autocomplete` so the native virtual keyboard does the right thing. Replace the desktop drop zone with a touch-first picker on mobile.
5. **Lists, feedback, and touch affordances.** Phase 05 — row-level actions (swipe to reveal Delete; tap-to-edit), loading skeletons on Inertia visits, scroll-to-first-error on validation, success/error toasts that are dismissible from the bottom.
6. **Verification.** Phase 06 — a real-device manual matrix on iPhone SE (the smallest target), iPhone 14 Pro (notch), Pixel 7 (Android Chrome), and iPad Mini (tablet boundary), plus the standard programmatic gates.

State management is page-local everywhere. Mobile sheet open/closed is a `ref<boolean>`. The mini-nav active section is derived from an `IntersectionObserver` instance owned by the section parent. Loading skeletons are rendered conditionally on Inertia's `processing` / `loading` flags, no global store.

## Phases

1. [Mobile Audit And Pain-Point Inventory](01-mobile-audit-and-pain-point-inventory.md)
2. [Shell, Navigation, And Safe-Area](02-shell-navigation-and-safe-area.md)
3. [Trip Workspace And Full-Screen Forms](03-trip-workspace-and-full-screen-forms.md)
4. [Inputs And OS-Native Pickers](04-inputs-and-os-native-pickers.md)
5. [Lists, Feedback, And Touch Affordances](05-lists-feedback-and-touch-affordances.md)
6. [Verification And Release](06-verification-and-release.md)

## Implementation Order

Run phases in order. Phase 01 freezes the pain-point list so subsequent phases work to it. Phase 02 stabilizes the shell so later phases are not chasing layout shifts. Phase 03 and 04 are the high-leverage UX shifts. Phase 05 is the polish layer. Phase 06 is the gate.

## Decisions Baked In (Override In Phase 01 If You Disagree)

These choices reduce ambiguity in the later phases. Each is challengeable in Phase 01.

| Decision | Rationale |
| --- | --- |
| **Full-screen `<Sheet>` for edit forms on mobile, inline on desktop** | The single biggest mobile UX win. Form fills the viewport, keyboard pushes nothing important offscreen, Cancel/Save are sticky at the top/bottom. Inline forms stay on desktop where there's room. |
| **No bottom-nav bar.** | This is a workspace app, not a feed. The existing top header + offcanvas mobile sheet (post-`navigation-uplift`) is the right pattern. A bottom nav would compete with the trip-tool segmented grid. |
| **Swipe-to-reveal Delete, tap-to-edit on rows.** | Matches Mail / Reminders / Things behavior. Edit becomes the primary tap (most common); Delete moves behind a swipe (rare). Keep visible Edit/Delete on desktop. |
| **Safe-area inset bottom and top respected on every full-bleed surface.** | Modals, lightboxes, sticky headers, the offcanvas sheet, and any new bottom-anchored toast. Use `pb-[max(env(safe-area-inset-bottom),theme(spacing.4))]` pattern. |
| **`inputmode` over `type` overrides.** | Stay with semantically correct `type` attributes (`number`, `email`, `tel`) and add `inputmode` / `enterkeyhint` for keyboard hints. Don't swap to `type="text"` for numerics. |
| **Loading skeletons via Inertia router events.** | Subscribe to `router.on('start', ...)` and `router.on('finish', ...)` to drive a thin top progress bar (existing in many Inertia apps via `@inertiajs/vue3`'s `Progress` plugin, if not already enabled). Per-section skeletons live in the page component using deferred-prop empty states (already used elsewhere). |
| **Toasts move to bottom on mobile, top-right on desktop.** | Thumb reach is the priority on phone; mouse is at the cursor on desktop. Single toast component with a responsive class. |
| **No PWA, no service worker, no offline mode in this module.** | Separate effort. This module is pure UX polish. |
| **No new design system / component library.** | Reuse existing shadcn-vue components (`Sheet`, `Dialog`, `DropdownMenu`, etc.). If a component is missing for a swipe-row pattern, add the smallest possible local component, not a third-party dependency. |

## Acceptance Criteria

- Audit document (Phase 01) lists every concrete pain point with viewport, page URL, screenshot reference, and severity.
- Every fix in Phases 02–05 maps to one or more audit findings; nothing fixed without a finding.
- All edit forms on `Trips/Show.vue` (reservations, packing items, costs, tasks, documents, reminders, itinerary items) open as a full-screen `<Sheet>` at viewport `<sm` and stay inline at `≥sm`.
- Date / time / number / currency / file / search inputs use the correct `type` + `inputmode` + `enterkeyhint` for the cleanest native virtual keyboard.
- Modals, the lightbox, and the offcanvas sheet respect safe-area insets on every notched device.
- Swipe-left on a packing / document / reservation row reveals a Delete action on touch devices; tap on the row body opens the edit sheet.
- A thin top-of-page progress bar shows for every Inertia visit; per-tab loading skeletons render while a panel is loading.
- Form submission scrolls the first invalid field into view (with `scroll-margin-top` accounting for the sticky header).
- Flash / toast messages render at the bottom of the viewport on mobile (`<sm`), top-right on desktop, and never overlap the safe area.
- The trip workspace's vertical "above the fold" content on iPhone SE (375 × 667 logical px) shows the hero title, dates, and at least one item of the active panel without scroll.
- All standard checks pass: `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent` (no PHP changes are anticipated, but the gate runs anyway).

## Out Of Scope

- Building a Progressive Web App, install prompt, or service worker (separate effort).
- Offline mode / local cache / sync queue.
- Native iOS/Android apps or Capacitor wrapping.
- Pull-to-refresh (deferred — Inertia visits already feel close enough).
- Haptic feedback (web standard `navigator.vibrate` is too inconsistent across iOS).
- Gesture-based panel switching (swipe between trip tool tabs) — adds complexity, doesn't pay back for a workspace app.
- Animation / transitions library beyond what's already used (Vue's `<Transition>` + Tailwind transition utilities are enough).
- A new design system or component library swap.
- Wholesale dark-mode rework (already shipped via `themes-plan`).
- Changes to copy / microcopy beyond what's needed to fit mobile widths.
- Accessibility-only work (separate audit if needed; this module is mobile UX).
