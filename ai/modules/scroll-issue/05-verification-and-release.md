# Phase 05 - Verification And Release

## Goal

Prove the scroll fix works without breaking routing, page rendering, or database safety.

## Browser Verification

Use Herd:

```bash
http://vacation-plan-app.test
```

Verify these routes:

- Trips index
- Trip show
- Trip search
- Settings layout
- Travel preferences

Viewport checks:

- `390x844`
- `820x1180`
- `1440x900`
- `1920x1080`

## Scroll Checks

For each viewport:

- Confirm only the document/page has a vertical scrollbar during normal page use.
- Confirm the desktop sidebar does not show its own vertical scrollbar.
- Confirm mobile sidebar is hidden by default.
- Confirm the mobile menu opens, closes, and does not leave the page locked.
- Confirm scroll position remains stable after closing the mobile menu.
- Confirm long trip content scrolls from top to bottom with one continuous gesture.

## Programmatic Verification

Run the minimum relevant checks after implementation:

```bash
npm run lint:check
npm run types:check
npm run build
php artisan test --compact
```

If only frontend shell files change and the full PHP suite is slow, run the existing focused frontend-safe feature tests first, then run the full suite before commit.

## Database Safety

This module should not require migrations, seeders, destructive Artisan commands, or database writes. Verification must not use:

- `php artisan migrate:fresh`
- `php artisan db:wipe`
- `php artisan migrate:refresh`
- destructive SQL

## Acceptance Criteria

- Build passes.
- Focused tests pass.
- Browser console has no new runtime or hydration errors.
- The scroll behavior is verified on desktop, tablet, and mobile.
- No database-destructive commands were used.

