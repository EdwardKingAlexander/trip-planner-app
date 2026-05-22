# Phase 02 - Dropdown Picker And Detection

## Goal

Add the app-wide timezone control to the user dropdown and initialize it from the browser timezone when appropriate.

## Status

- Status: verified
- Owner: Codex
- Depends on: Phase 01
- Blocker: none

## Audit Inputs

- `resources/js/components/UserMenuContent.vue:36` owns the current settings/logout dropdown contents.
- `resources/js/components/NavUser.vue:56` renders that menu from the sidebar shell.
- `resources/js/pages/Trips/Show.vue:237` proves browser timezone detection already uses `Intl.DateTimeFormat().resolvedOptions().timeZone`.
- `resources/js/pages/Trips/Show.vue:222` shows page-level timezone lists are currently passed as raw string arrays.

## Vue Contract (frozen)

```ts
type TimezonePreference = {
    value: string;
    default: string;
    is_default: boolean;
};
```

The dropdown reads `page.props.timezones` and `page.props.auth.timezone`, renders a native select with every timezone option, and posts `PATCH /settings/timezone` with `{ timezone }`.

## Detection Rule (frozen)

On mount, if `auth.timezone.is_default === true`, the browser returns a valid timezone in the shared option list, and the client has not already attempted detection for the current user/timezone, submit the detected timezone. Manual dropdown changes always submit immediately.

## Deliverables

- `resources/js/components/UserMenuContent.vue`
- Optional reusable timezone picker component if the existing local component cannot be reused cleanly.
- `resources/js/types` shared prop typing if current types require it.

## Acceptance Criteria

- The menu shows the current timezone.
- The dropdown contains the full shared timezone list.
- Browser detection does not repeatedly submit after the first attempt.
- Manual selection updates the persisted preference and refreshes shared props.

## Risks

- Dropdown menus can close when interacting with form controls; mitigation is to use the existing dropdown primitives carefully and keep the select interaction simple.
- Auto-detect could overwrite a prior manual choice; mitigation is the server-shared `is_default` guard.

## Out Of Scope

- IP-based timezone lookup.
