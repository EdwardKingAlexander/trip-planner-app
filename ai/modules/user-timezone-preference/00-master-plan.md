# User Timezone Preference Master Plan

## Goal

Authenticated users get an application-wide timezone preference that is detected from their browser on first use, visible in the user dropdown, selectable from the complete IANA timezone list, persisted to their travel preferences, and used anywhere the app needs the user's home/application timezone.

## Status

- Status: verified
- State file: `ai/state/user-timezone-preference.json`
- Last updated: May 22, 2026

## Problem

The app already shares the full timezone list globally through Inertia, but it only exposes raw identifiers and no current application-wide preference payload for the shell to render or update (`app/Http/Middleware/HandleInertiaRequests.php:44`, `app/Http/Middleware/HandleInertiaRequests.php:53`). Travel preferences persist `home_timezone`, yet the only update surface is the full settings page and it currently uses a freeform text input, which is easy to mistype and not available from the dropdown (`app/Http/Controllers/Settings/TravelPreferenceController.php:20`, `resources/js/pages/settings/TravelPreferences.vue:40`). The user menu only links to Settings and has no timezone picker or browser-detection handshake (`resources/js/components/UserMenuContent.vue:36`). Server-side validation accepts valid timezone identifiers, but there is no lightweight endpoint for detected client timezone updates (`routes/settings.php:25`, `routes/settings.php:28`).

## Strategy

1. Add a shared timezone preference contract so every Inertia page receives the persisted timezone, the browser-detect endpoint target, and the canonical options list without duplicating controller props.
2. Add a focused settings endpoint that updates only the app timezone so the dropdown can save immediately without submitting unrelated travel-preference fields.
3. Replace freeform timezone editing with a reusable timezone picker so the dropdown and travel settings page both use the same complete option list.
4. Detect the browser timezone once per user session/client and persist it only when the user has not already chosen a non-default preference.
5. Verify persistence, validation, Inertia shared props, and frontend type/build behavior so the app-wide preference is safe across authenticated pages.

## State Management

- DB row: `user_travel_preferences.home_timezone` is the durable owner of the selected application timezone. It is written by `TravelPreferenceController` full settings updates and by the new focused timezone endpoint.
- Server-computed prop: `HandleInertiaRequests` owns `auth.timezone`, including the persisted value, default app timezone, and whether the value is still default-backed.
- Vue ref/form state: `UserMenuContent.vue` owns transient dropdown selection state and posts changes through Inertia; it is rehydrated from `page.props.auth.timezone`.
- Browser-detected value: the client computes `Intl.DateTimeFormat().resolvedOptions().timeZone`; it is only treated as a suggestion until server validation accepts it.
- Persisted client guard: `localStorage` owns a small "timezone detection attempted" marker per user/timezone so the browser detection does not repeatedly overwrite the app on every page visit.

## Phases

1. [Phase 01 - Shared Contract And Endpoint](01-shared-contract-and-endpoint.md)
2. [Phase 02 - Dropdown Picker And Detection](02-dropdown-picker-and-detection.md)
3. [Phase 03 - Settings Page Alignment](03-settings-page-alignment.md)
4. [Phase 04 - Tests And Verification](04-tests-and-verification.md)

## Implementation Order

The shared prop and endpoint ship first because they define the only persistence contract. The dropdown can then be wired without inventing client-only state. The existing settings page is aligned after the shared picker exists, and verification closes the loop with backend tests plus frontend lint/types/build.

## Acceptance Criteria

- The authenticated user dropdown shows the current application timezone and a complete IANA timezone dropdown.
- A browser timezone is detected with `Intl.DateTimeFormat().resolvedOptions().timeZone` and saved automatically only when the user still has the app default/no explicit timezone.
- Manual timezone selection persists immediately and updates application-wide Inertia shared props after navigation/reload.
- Invalid or shorthand timezone values such as `PST` are rejected server-side.
- The travel settings page no longer uses a freeform timezone text input.
- Run `php artisan test --compact`, `npm run lint:check`, `npm run types:check`, `npm run build`, `vendor/bin/pint --dirty --format agent`, and `php artisan wayfinder:generate --with-form --no-interaction` because routes change.

## Decisions Locked In (planning pass - May 22, 2026)

- The durable app-wide timezone is `user_travel_preferences.home_timezone`; adding a second user timezone column would split state that already exists.
- Browser detection may initialize a default-backed preference, but any manual user choice wins after that.
- The dropdown lives in the existing user menu because that is the app-wide account control already present in both sidebar and header layouts.
- The complete timezone option list continues to come from `TimezoneLookup::identifiers()` via shared Inertia data.
- The focused endpoint updates only `home_timezone` and leaves currency/profiles/templates unchanged.

## Out Of Scope

- Per-trip destination timezone rules remain owned by the existing trip timezone feature.
- Retrofitting every historical timestamp display to user-local time is deferred unless a surface already consumes the shared app timezone.
- Guest-user timezone preferences are not included; this is for authenticated application users.
