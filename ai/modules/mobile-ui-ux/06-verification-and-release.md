# Phase 06 - Verification And Release

## Goal

Prove the mobile UX shifts actually land on the devices users hold. Real-device pass on at least four physical or simulator targets, every audit finding cross-checked off, full programmatic gate suite, and a documented handoff for the next person.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 01–05
- Blocker: none

## Real-Device Verification Matrix

Test on at least these targets. Simulators are acceptable for shape; physical devices are required for at least one iOS and one Android.

| Target | Reason |
| --- | --- |
| iPhone SE (3rd gen, 375 × 667 logical px) | The smallest viable target. If it works here, it works everywhere on iOS. |
| iPhone 14 / 15 (390 × 844) | The mainstream notch device. Validates safe-area top + home-indicator bottom. |
| Pixel 7 (Android Chrome, 412 × 915) | Validates Android Chrome behavior — different `accept=` handling, different keyboard. |
| iPad Mini (820 × 1180 portrait) | The boundary where breakpoints shift from mobile to desktop. |

Optional but recommended:

- iPhone 14 Pro Max (430 × 932) — validates wider mobile.
- iOS Safari with Reader Mode disabled, Accessibility Display Zoom on (Larger Text setting) — validates layout under font-size scaling.

For each target, run the verification matrix below.

### Verification Matrix Items

For each target × matrix item, mark pass / fail with a screenshot reference.

#### Shell + safe-area (Phase 02)

1. Sign in. Top progress bar appears for the navigation. The safe-area top is respected — no content is under the notch.
2. Open the offcanvas mobile sheet. Body scroll behind it is locked. Tap outside to close. Tap the X to close. Use Tab key (if external keyboard) — focus stays trapped inside.
3. Trigger a flash message (e.g., add a packing item). Toast renders bottom-center on phone, top-right on iPad/desktop. Close button is reachable with one thumb.
4. Open the lightbox (tap an image attachment). Close button is above the home indicator on a notched device. Esc closes (with external keyboard).

#### Trip workspace + full-screen forms (Phase 03)

5. Open `/trips/{id}` on iPhone SE. Confirm the hero title, dates, and at least one item of the active panel are visible without scrolling.
6. Tap Edit on a packing item. A full-screen sheet opens. Cancel and Save are at the top, sticky. Tapping outside the sheet does not dismiss it (drag-to-dismiss is not in scope).
7. With the keyboard up, the focused input stays visible. The Save button (sticky bottom or sticky top) does not get hidden.
8. Edit a reservation. Confirm `<FormSectionPills>` renders at the top with at least three pills (Basics, Flight details, Attachments). Tap a pill — it scrolls smoothly to the section.
9. With validation errors triggered (submit with empty required field), the page scrolls to the first invalid field and focuses it.
10. Tap Cancel. The sheet closes. The list is unchanged. Tap Save. The sheet closes and the row reflects the change.
11. Switch to iPad Mini portrait. The same Edit tap opens the inline form (or the desktop sheet variant) — full-screen sheet does not appear.

#### Inputs + native pickers (Phase 04)

12. Tap a fee input on the new flight-details section. The decimal pad appears (iOS). Confirm comma vs period works for locale.
13. Tap a `type="date"` input. The OS wheel picker appears (iOS) or date dialog (Android).
14. Tap a `type="datetime-local"` input. The combined wheel picker appears.
15. On the documents tab, tap "Choose files". The OS picker offers Camera / Photo Library / Browse on iOS.
16. Stage two files. Confirm the staged-file list appears with names + sizes + Remove buttons.
17. On the reservation card, repeat: tap Add attachment, stage a file. Confirm the staged file is visible (no "No file chosen" misleading text).
18. Tap the search input on `/trips/search`. Confirm the keyboard's Enter key reads "Search". Hitting it submits.

#### Lists + feedback + gestures (Phase 05)

19. On a packing list, swipe-left on a row. Delete reveals. Tap Delete — row deletes. Tap elsewhere first — Delete hides.
20. Tap on the row body (not the swipe area). The edit sheet opens. (Confirm: the swipe doesn't fight the tap.)
21. On the notifications page, observe the loading skeleton on first paint over a slow connection (Chrome DevTools 3G throttle).
22. Trigger a deliberate validation error in a long form. Page scrolls to the first invalid field and focuses it.
23. Trigger a success — toast auto-dismisses after ~4 s. Trigger an error — toast auto-dismisses after ~6 s. Trigger three flashes in a row — they queue, one at a time.
24. Tap any primary button — confirm the visible `active:` state (subtle scale or background change).

#### Cross-cutting

25. Cycle through all five themes. Confirm none of the new mobile components (EditSheet header bar, swipe row Delete, toast bottom-banner, file picker button) become illegible on any theme.
26. Rotate to landscape. Confirm the EditSheet still fills the viewport, no content overflows the safe area.
27. Run Lighthouse Mobile on `/trips/{id}` (logged in via DevTools cookie injection or Lighthouse CI). Confirm Performance ≥ 70, Accessibility ≥ 90, Best Practices ≥ 90.
28. Slow 3G throttle: visit a trip — top progress bar appears, page renders skeletons where defined, no white-screen wait.

### Audit Cross-Check

Phase 01's `audit_findings` list every pain point. Walk it row by row:

- Every `critical` finding: must show as fixed.
- Every `high` finding: must show as fixed.
- Every `medium` finding: aim for fixed; document any deferrals with reason.
- `low` findings may be deferred.

Per-finding cross-check goes into `verification[]` in `ai/state/mobile-ui-ux.json`.

## Programmatic Verification

All must pass:

- `php artisan test --compact` — full Pest suite (no PHP changes expected, but the gate runs).
- `npm run lint:check`
- `npm run build`
- `npm run types:check` (after build).
- `vendor/bin/pint --dirty --format agent` (clean — no PHP edits expected).

If browser tests are written for Phases 03–05:

- `php artisan test --compact tests/Browser/MobileEditSheetTest.php`
- `php artisan test --compact tests/Browser/MobileFilePickerTest.php`
- `php artisan test --compact tests/Browser/MobileSwipeRowTest.php`
- `php artisan test --compact tests/Browser/ScrollToFirstErrorTest.php`
- `php artisan test --compact tests/Browser/ToastDismissTest.php`

## Theme + Accessibility Audit

- All five themes: walk the trip workspace at 390 × 844 in each. Confirm the EditSheet header, sticky form pills, swipe-row Delete background, file picker button, and toast banner remain legible (WCAG AA contrast).
- VoiceOver pass on iPhone (one full task: open trip → edit packing item → save). Confirm:
  - The EditSheet announces as "Edit packing item, dialog".
  - Cancel and Save are reachable in tab order.
  - Form pills are announced as buttons.
  - Toast announcement uses `role="status"` (success) / `role="alert"` (error).
- TalkBack pass on Android: same task.

## Manual Verification Script

1. Pick one device per target row.
2. For each device, walk verification matrix items 1–28 in order.
3. Cross-check audit findings.
4. Capture screenshots in `ai/audit/mobile-2026-05-14/post-fix/<device>/<item>.png`.
5. Update `ai/state/mobile-ui-ux.json` `verification[]`.

## Regression Watch List

- `responsive-ui-uplift` work (touch targets, mobile grid trip nav) must still hold.
- `scroll-issue` single-scroll-owner rule must still hold (the EditSheet introduces an inner scroll *inside the sheet* which is acceptable; the page beneath stops scrolling while the sheet is open).
- `navigation-uplift` parity (sidebar = mobile sheet) must still hold.
- `notification-deep-links` anchor scroll must still land below the sticky header (verified via Phase 02's `scroll-margin-top` rules).
- `themes-plan` themes must all render the new components legibly.
- `packing-attribution`, `document-uploads`, `flight-details` panels must all behave as designed inside the new EditSheet wrapper.
- The existing realtime fan-out (`live-trip-collaboration`) continues to refresh the trip view while the EditSheet is open — confirm a remote update does not corrupt the open form.

## Handoff

- Update `ai/state/mobile-ui-ux.json` with `status: verified`, fill `verification[]` with per-device pass/fail and screenshot references.
- `handoff.summary` covers:
  - Where the EditSheet pattern lives (`resources/js/components/EditSheet.vue`) and how to wrap a new edit form.
  - Where the SwipeRow lives (`resources/js/components/SwipeRow.vue`) and how to add it to a new list.
  - Where safe-area utilities are defined (`resources/css/app.css`) and which container classes to apply.
  - How to run the Lighthouse Mobile gate locally.
  - How to extend `scrollToFirstError` for a new nested form (add `data-error-target` attributes).
  - The audit / fix loop convention: any new mobile pain point gets added to `audit_findings` with a phase tag, then fixed in a follow-up module.

## Acceptance Criteria

- All real-device matrix items pass on at least one iOS and one Android device.
- All `critical` and `high` audit findings from Phase 01 are fixed (or have a documented reason for deferral).
- Lighthouse Mobile on `/trips/{id}` returns Performance ≥ 70, Accessibility ≥ 90, Best Practices ≥ 90.
- All programmatic gates pass.
- `ai/state/mobile-ui-ux.json` is updated with `status: verified` and the full verification log.
- `ai/modules/README.md` index entry is in place.

## Out Of Scope

- Visual regression / screenshot diffing tooling.
- Performance benchmarking beyond Lighthouse score.
- Marketing copy / changelog entries.
- WCAG AAA-level accessibility (AA is the bar).
- A new module for fixing `low`-severity findings — those can be folded into a follow-up polish pass if the user requests it.
