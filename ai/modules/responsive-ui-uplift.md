# Responsive UI Uplift Plan

Last updated: 2026-05-05
Status: implemented and verified

## Investigation Summary

The app works on small screens, but it is not comfortably mobile or tablet friendly because several interactive surfaces are sized for desktop precision instead of touch use. The live Herd audit at `http://vacation-plan-app.test` found a 28px sidebar trigger, 32-36px links/buttons in the authenticated shell and trip workspace, and compact two-column date/time grids at phone width.

The trip detail page also uses a horizontal panel rail for core tools. That keeps the page from hard overflowing, but it hides Packing, Tasks, Documents, Imports, and Sharing offscreen on mobile/tablet and makes the workspace feel like a desktop UI squeezed into a phone.

The visual system is coherent but too muted. The authenticated trip workspace leans heavily on beige, brown, and dark teal, which makes the UI feel flat instead of uplifting. The public/auth refresh established a travel direction; the authenticated workspace needs brighter travel color, clearer action hierarchy, and lighter surfaces.

## Responsive Fix Plan

1. Increase core navigation touch targets.
   - Make the app header/sidebar trigger at least 44px.
   - Keep sidebar links visually compact on desktop, but not undersized on touch devices.

2. Replace hidden horizontal trip-tool navigation with a mobile-first segmented grid.
   - Use a two-column mobile grid so every trip tool is visible without sideways scrolling.
   - Switch to a horizontal/tablet-friendly rail only where there is enough space.

3. Make dense forms mobile-first.
   - Date/time and paired inputs should be one column by default.
   - Upgrade to two columns only above narrow-phone widths.
   - Give inputs, selects, textareas, and buttons a consistent 44px minimum touch height.

4. Fix action clusters.
   - Allow print/export actions to wrap cleanly.
   - Avoid three-column controls on widths where labels/icons feel cramped.

## UI Uplift Plan

1. Update the authenticated travel palette.
   - Use sky, sea, coral, leaf, and sun accents over light blue/cream surfaces.
   - Reduce brown/beige dominance while preserving warmth.

2. Improve hierarchy and rhythm.
   - Use softer page backgrounds, clearer card borders, and more intentional hero stats.
   - Keep operational trip tools dense enough for repeat use.

3. Make empty and form states more encouraging without adding instructional clutter.
   - Improve icon/accent treatment.
   - Keep copy short and product-specific.

## Implementation Scope

- `resources/css/app.css`
- `resources/js/components/AppSidebarHeader.vue`
- `resources/js/components/AppSidebar.vue`
- `resources/js/pages/Trips/Index.vue`
- `resources/js/pages/Trips/Show.vue`

## Verification Plan

- Run Prettier/ESLint/type checks for Vue changes.
- Run the affected trip feature tests.
- Build Vite assets.
- Re-run viewport audit at mobile, tablet, and desktop widths against Herd.

## Verification Results

- `npm run lint:check` passed.
- `npm run types:check` passed.
- `php artisan test --compact tests/Feature/TripManagementTest.php tests/Feature/TripImportExportTest.php` passed with 9 tests and 30 assertions.
- `npm run build` passed.
- Herd viewport audit passed at 390px, 820px, and 1440px with no page JavaScript errors and no document-level horizontal overflow on Trips index or Trip detail.
