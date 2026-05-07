# Phase 05 - Verification And Release

## Goal

Prove every theme works across every authenticated screen, gate the release behind the standard test/lint/build/format suite, and capture handoff notes so a future agent does not have to re-derive the contract.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 01–04
- Blocker: none

## Verification Matrix

For each theme × appearance combination (5 × 2 = 10 cells):

- Trip index (`/trips`)
- Trip show (`/trips/{trip}`) including itinerary, reservations, budget, packing, and documents tabs
- Dashboard
- Settings → Appearance (the picker itself)
- Settings → Profile, Password, Two-Factor
- Login, Register, Forgot Password (cookie path)

Spot-check against:

- Sidebar (collapsed and expanded)
- Header (logged in)
- Cards, dialogs, dropdowns, popovers, selects
- Form inputs, checkboxes, alerts (success + destructive)
- Charts (each `chart-1..5` token visible somewhere)
- Empty states and skeletons (deferred props)

## Programmatic Verification

Run from project root:

- `php artisan test --compact` — full Pest suite, including the new `ThemeControllerTest` and appearance browser test.
- `npm run lint:check` — ESLint clean.
- `npm run types:check` — Vue + TS clean. Run after `npm run build` so Wayfinder route files are regenerated for the new `settings.theme.update` endpoint.
- `npm run build` — production Vite build.
- `vendor/bin/pint --dirty --format agent` — PHP formatting.

All five must pass before this phase is marked `verified`.

## Manual Verification Script

1. Log in as a test user.
2. Visit `/settings/appearance`.
3. For each of the five themes:
   - Click the swatch.
   - Verify the page restyles with no flash and no console errors.
   - Toggle light → dark → system.
   - Hard-reload and confirm the same theme + appearance returns with no flash.
4. Log out, return to the login page, change the theme, log in as a different user.
5. Confirm the second user's stored theme overrides the cookie value on first authenticated render.

## Regression Watch List

- The legacy `travel-*` utilities (now token-driven) — check `TripShow`, trip cards, and the dashboard hero stripe specifically.
- Sidebar sticky behavior must not change (scroll-issue module shipped this; do not regress).
- Print stylesheet, if any, should still render in coastal-light to avoid wasting ink.
- SSR first paint — view source on a hard refresh and confirm `data-theme` is on the `<html>` tag before any JS runs.

## Handoff

- Update `ai/state/themes-plan.json` with `status: verified`, fill `verification[]` with the exact commands and results, and write a one-paragraph `handoff.summary` covering: token contract location, how to add a sixth theme later, where the registry lives, and which files touch persistence.
- Note explicitly that the cookie value is the SSR source of truth and that the DB column is authoritative for signed-in users on new devices.
- Add a Decision Log entry to `ai/modules/STATE.md` if the project-level state file tracks cross-module decisions, otherwise leave STATE.md alone.

## Acceptance Criteria

- All programmatic checks pass.
- Manual script completes without console errors, layout shifts, or flashes of unstyled content.
- `ai/state/themes-plan.json` reflects the final state.
- A future agent can add a sixth theme by editing only: `resources/js/types/ui.ts`, `resources/js/lib/themes.ts`, `app/Enums/Theme.php`, and `resources/css/app.css`. No other files should need to change to add a theme.

## Out Of Scope

- Performance benchmarking of theme switching.
- Automated visual regression / screenshot diffing.
- Marketing copy for the new feature.
