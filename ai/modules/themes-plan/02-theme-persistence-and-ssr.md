# Phase 02 - Theme Persistence And SSR

## Goal

Make the chosen theme survive reloads, SSR, and devices by persisting it on the user record and threading the cookie value through Inertia's shared props so the first paint matches the user's preference.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 01
- Blocker: none

## Planned Changes

### Migration: `database/migrations/{timestamp}_add_theme_to_users_table.php`

- Add `string('theme', 32)->default('coastal')` to `users`.
- Down migration drops the column.
- Run with `php artisan make:migration add_theme_to_users_table --table=users` then edit; `php artisan migrate` for local Herd.

### `app/Models/User.php`

- Add `'theme'` to `$fillable`.
- No cast needed — plain string. Validation against the allowed slug list happens in the controller.

### `app/Enums/Theme.php` (new)

- Backed string enum: `Coastal`, `Sunset`, `Forest`, `Midnight`, `Sandstone`.
- Provides `Theme::values(): array` for validation rules and a `Theme::default(): self` constant for fallbacks.
- Mirrors the JS `Theme` union exactly so backend and frontend stay in lockstep.

### `app/Http/Controllers/Settings/ThemeController.php` (new)

- Single `update(Request $request)` action.
- Validates `theme` against `Rule::enum(Theme::class)`.
- Persists `auth()->user()->update(['theme' => $validated['theme']])`.
- Returns `back()` so Inertia preserves the current page.

### `routes/settings.php` (or wherever appearance routes live — reuse existing convention)

- `Route::patch('settings/theme', [ThemeController::class, 'update'])->middleware(['auth'])->name('settings.theme.update');`
- Run `npm run build` (or rely on `composer run dev`) so Wayfinder regenerates `resources/js/actions` / `resources/js/routes` for the new endpoint.

### `app/Http/Middleware/HandleInertiaRequests.php`

- Extend `share()` with `'theme' => $request->user()?->theme ?? $request->cookie('theme') ?? Theme::default()->value`.
- Cookie fallback covers guest pages (login, register) so unauthenticated theme picks still apply.

### `resources/views/app.blade.php`

- Read the `theme` cookie server-side and emit `data-theme="..."` on `<html>` so the very first paint already has the correct tokens. Default to `coastal` if absent.
- Already does the equivalent for the `appearance` cookie (`class="dark"` toggling) — follow the same shape.

### `resources/js/composables/useTheme.ts` (extend from Phase 01)

- On mount, prefer the Inertia shared `theme` prop over localStorage when both exist and disagree (server is authoritative for logged-in users).
- When `updateTheme` runs and a `userId` is present in shared props, fire a Wayfinder-typed `router.patch` to `settings.theme.update` with `{ theme }` and `{ preserveScroll: true, preserveState: true }`. Do not block the local optimistic update on the network call.

### `tests/Feature/Settings/ThemeControllerTest.php` (new, Pest)

- `it('persists a valid theme for the authenticated user')`.
- `it('rejects an unknown theme')` → 422.
- `it('requires authentication')` → redirect to login.
- Use `RefreshDatabase` and the existing `User::factory()`.

## State Management

- DB `users.theme`: cross-device, authoritative for signed-in users.
- Cookie `theme`: SSR + first-paint, also handles guest persistence.
- Inertia shared prop `theme`: hands the resolved value to Vue without a second request.
- localStorage `theme`: optimistic client cache; reconciled with the shared prop on mount.

Write fan-out on `updateTheme`: localStorage immediately → cookie immediately → DOM immediately → background `router.patch` if authenticated.

## Acceptance Criteria

- Logged-in user changes theme, hard-reloads, and sees the same theme without a flash.
- Logged-out user changes theme on the login page, navigates, and the choice is remembered via cookie.
- New device login pulls the user's stored theme on first authenticated page render.
- Theme controller test file passes with `php artisan test --compact --filter=ThemeController`.
- Migration runs cleanly on a fresh database.

## Out Of Scope

- The actual theme color tokens (Phase 03).
- The settings UI surface (Phase 04).
- Migrating the legacy `travel-*` hex values to tokens (Phase 03).
