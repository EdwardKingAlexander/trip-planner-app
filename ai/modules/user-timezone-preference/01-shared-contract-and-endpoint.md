# Phase 01 - Shared Contract And Endpoint

## Goal

Expose the current application timezone as shared Inertia data and add a narrow authenticated endpoint to persist timezone-only updates.

## Status

- Status: verified
- Owner: Codex
- Depends on: none
- Blocker: none

## Audit Inputs

- `app/Http/Middleware/HandleInertiaRequests.php:44` defines the global shared prop boundary.
- `app/Http/Middleware/HandleInertiaRequests.php:53` already shares the canonical timezone list.
- `app/Http/Controllers/Settings/TravelPreferenceController.php:20` persists the full travel preference form.
- `routes/settings.php:26` shows settings PATCH routes already live in the verified authenticated group.

## Endpoint Contract (frozen)

```php
PATCH /settings/timezone
name: settings.timezone.update
middleware: auth, verified
payload: { timezone: string }
validation: ['required', 'timezone']
result: updateOrCreate([], ['home_timezone' => $validated['timezone']])
redirect: back()->with('success', 'Timezone updated.')
```

## Shared Prop Contract (frozen)

```php
'auth' => [
    'user' => $request->user(),
    'timezone' => [
        'value' => $request->user()?->travelPreference?->home_timezone ?? config('app.timezone'),
        'default' => config('app.timezone'),
        'is_default' => $request->user()?->travelPreference?->home_timezone === null,
    ],
],
```

## Deliverables

- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Controllers/Settings/TravelPreferenceController.php`
- `routes/settings.php`
- Generated Wayfinder route files after route generation.

## Acceptance Criteria

- Authenticated Inertia responses include `auth.timezone.value`, `auth.timezone.default`, and `auth.timezone.is_default`.
- `PATCH /settings/timezone` persists a valid timezone without requiring currency/profile/template fields.
- Invalid timezone submissions redirect with validation errors and do not persist.

## Risks

- Shared props can grow too large; mitigation is to share only current timezone metadata and reuse the existing lazy `timezones` list.
- `firstOrCreate` may create a preference with model defaults; mitigation is to update only `home_timezone` and preserve existing columns.

## Out Of Scope

- Changing trip destination timezone persistence.
