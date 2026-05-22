# Phase 04 - Tests And Verification

## Goal

Prove the timezone preference flow works at the persistence, shared-prop, validation, and frontend build levels.

## Status

- Status: verified
- Owner: Codex
- Depends on: Phases 01-03
- Blocker: none

## Audit Inputs

- `tests/Feature/TripTimezoneTest.php:1` covers existing timezone behavior and conventions.
- `tests/Feature/TripImportExportTest.php:121` already asserts travel preference home timezone persistence in a broader workflow.
- `routes/settings.php:28` is the existing full travel-preference update route that must keep working.

## Test Contract (frozen)

Feature coverage should assert:

- Shared Inertia props include the effective timezone.
- The focused endpoint persists a valid timezone.
- The focused endpoint rejects invalid shorthand timezone codes.
- A full travel settings update still persists `home_timezone` and related fields.

## Deliverables

- New or updated Pest feature tests.
- Updated `ai/state/user-timezone-preference.json` with verification command outputs.

## Acceptance Criteria

- `php artisan test --compact`
- `npm run lint:check`
- `npm run types:check`
- `npm run build`
- `vendor/bin/pint --dirty --format agent`
- `php artisan wayfinder:generate --with-form --no-interaction`

## Risks

- Existing unrelated dirty work may affect full-suite results; mitigation is to report any failures with file/test names and avoid reverting unrelated changes.

## Out Of Scope

- Browser automation is optional unless the CLI verification indicates a UI/runtime issue.
