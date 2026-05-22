# Phase 03 - Settings Page Alignment

## Goal

Align the full travel settings page with the new timezone selection model so users never have to type timezone identifiers manually.

## Status

- Status: verified
- Owner: Codex
- Depends on: Phase 02
- Blocker: none

## Audit Inputs

- `resources/js/pages/settings/TravelPreferences.vue:40` currently renders home timezone as a freeform input.
- `resources/js/pages/settings/TravelPreferences.vue:18` stores `home_timezone` in the full settings form.
- `app/Http/Controllers/Settings/TravelPreferenceController.php:22` validates full settings with Laravel's `timezone` rule.

## UI Contract (frozen)

The travel settings page uses the same timezone option source as the dropdown. The saved field remains `home_timezone` so full-form saves and dropdown saves converge on the same database column.

## Deliverables

- `resources/js/pages/settings/TravelPreferences.vue`
- Shared timezone picker component if extracted in Phase 02.

## Acceptance Criteria

- Travel settings timezone selection uses a dropdown/list with every timezone option.
- Existing settings save behavior for currency, traveler profiles, and packing templates is unchanged.
- The selected timezone defaults from the persisted preference.

## Risks

- Page props may not currently include `timezones` in the TypeScript shape; mitigation is to type the local prop from `usePage`.

## Out Of Scope

- Redesigning the travel settings page beyond the timezone input.
