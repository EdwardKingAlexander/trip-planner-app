# Phase 02 - Notes Fields And Edit UI

## Status

- Status: `verified`
- Priority: `high`

## Goal

Expose notes and edit controls directly in `Trips/Show.vue` for every main planning section.

## Implementation Plan

1. Show existing notes/descriptions on list cards.
2. Add notes textareas to create forms where missing.
3. Add inline edit buttons and compact edit forms for each entry type.
4. Preserve the active trip panel and scroll position after updates.
5. Use generated routes after route changes are registered.

## Verification

- Ran `php artisan wayfinder:generate --with-form --no-interaction`.
- Passed `npm run types:check`.
- Passed `npm run lint:check`.
