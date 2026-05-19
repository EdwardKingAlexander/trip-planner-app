# Phase 04 - Inputs And OS-Native Pickers

## Goal

Make every input on the app summon the right native virtual keyboard / OS picker on the first tap. Numerics get the decimal pad; emails get the @ key; URLs get the slash; search inputs get the right Enter behavior; date and time inputs use the OS wheel picker; the desktop drag-and-drop file zone is replaced on touch with a touch-first picker that shows what was selected.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 03 (forms now live in the EditSheet so input changes apply consistently)
- Blocker: none

## Audit Inputs

- Every `<input>` and `<textarea>` in `resources/js/pages/**/*.vue`. The trip workspace alone has fields across 7 panel forms.
- Per-input attributes today: most use `type="text"`, `type="number"`, `type="date"`, `type="datetime-local"`, `type="email"` correctly. Few use `inputmode`, `enterkeyhint`, or `autocomplete`.
- The documents-tab drop zone (post-`document-uploads` Phase 03): dashed border, drag-active highlight, "Choose files" button + drag-and-drop copy. The copy is misleading on touch.
- All audit findings tagged `category: inputs | keyboard`.

## Strategy

Three threads:

1. **Right keyboard, every time.** Add `inputmode`, `enterkeyhint`, and `autocomplete` to every input via a small audit pass. No new component — just attribute additions.
2. **Native OS pickers for date / time.** Confirm `type="date"`, `type="time"`, and `type="datetime-local"` are used consistently and have no value-format bugs that defeat the OS picker on iOS Safari (a common one: assigning ISO 8601 with milliseconds to a `datetime-local` input prevents the wheel picker).
3. **Touch-first file picker.** Below `<sm`, the documents-tab drop zone hides its dashed border and "drag and drop" copy and renders only the styled "Choose files" button + a clearly-visible "Selected: …" line. Above `≥sm`, the drop zone stays as today.

## Planned Changes

### 1. Input attribute pass

A non-exhaustive map of inputs to update. The exact list comes from the Phase 01 audit, but expected updates include:

| Field semantics | Existing | Add |
| --- | --- | --- |
| Currency / fee amount | `type="number" step="0.01"` | `inputmode="decimal"` |
| Quantity (packing) | `type="number"` | `inputmode="numeric"` |
| Phone number | `type="tel"` | `autocomplete="tel"` |
| Email | `type="email"` | `autocomplete="email"`, `enterkeyhint="next"` |
| URL (rare) | `type="url"` | `inputmode="url"` |
| Search box (`/trips/search`) | `type="search"` | `inputmode="search"`, `enterkeyhint="search"`, `autocomplete="off"` |
| Title / label | `type="text"` | `enterkeyhint="next"`, `autocomplete="off"` |
| Notes (textarea) | (default) | `enterkeyhint="enter"` |
| Currency 3-letter (flight_details) | `type="text" maxlength="3"` | `inputmode="text"`, `autocapitalize="characters"`, `autocomplete="off"` |
| Postal code / zip | `type="text"` | `inputmode="numeric"` (US) or `inputmode="text"` (intl) |
| Confirmation number / PNR | `type="text"` | `autocapitalize="characters"`, `autocomplete="off"` |

A single helper or wrapper component is **not** required. Just add the attributes inline where the inputs live — there's no template-soup risk and a wrapper would obscure the actual input semantics from a quick read.

### 2. Date/time input correctness

`type="date"`:

- Value must be `YYYY-MM-DD` (ISO 8601 short form). Confirm the existing helpers (e.g. `toDateInput(value)` if it exists) emit this exactly. No `Z` suffix, no time component.

`type="datetime-local"`:

- Value must be `YYYY-MM-DDTHH:mm` (no timezone, no seconds, no milliseconds). The existing `toDateTimeLocal()` helper at the top of `Trips/Show.vue` looks correct, but verify.
- iOS Safari renders these as the wheel picker. Android Chrome renders as a date+time dialog.

`type="time"`:

- Value must be `HH:mm`. No use case in the app today as far as I can see; flag if found.

If any field needs *both* a calendar UI and a free-form text fallback, **do not** introduce a third-party datepicker library. The native input is fine; the gap is the timezone display, which is already handled separately in the trip workspace.

### 3. Touch-first file picker

The documents-tab drop zone (today's markup is post-`document-uploads`):

- Hide the dashed border, the drag-active highlight handlers, and the "or drag and drop here" copy on `<sm`.
- Show only:
  - A `<label>` styled as a button (`travel-button-primary`) wrapping a hidden `<input type="file" multiple accept="...">`. The label opens the OS file picker on tap.
  - A "Selected: filename.pdf, other.jpg" line that always renders below the label when the staged files list is non-empty. Each line has a Remove button.
- Above `≥sm`, the drop zone keeps today's markup.

The `accept` attribute matters more on mobile than on desktop — iOS surfaces a Camera / Photo Library / Browse menu when `accept="image/*,.pdf"` is set correctly. Confirm the documents-tab and reservation-card upload pickers both have:

```html
accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,image/*,application/pdf"
```

(The double specification — extensions and MIME types — covers the largest set of mobile browsers.)

### 4. Reservation-card attachment picker

The reservation-card upload (post-`document-uploads` Phase 04) currently uses a bare `<input type="file">` that resets `input.value = ''` after staging — the native input then displays "No file chosen" even though files are staged. Fix the same way as #3 above:

- Hide the native input.
- Show a styled `<label>` "Add attachment" button that opens the picker.
- Always render the "Selected: …" line when the staged files list is non-empty.

(This is the same bug the user flagged separately. The fix lives in this phase to keep the input UX consistent across the app.)

### 5. Autofill for trip create / settings

For form fields that map to common autofill categories:

- Email → `autocomplete="email"`
- Name → `autocomplete="name"` (split into `given-name` / `family-name` if the field is split)
- Phone → `autocomplete="tel"`
- Address → `autocomplete="street-address"` / `address-line1` / `address-line2` / `address-level2` (city) / `postal-code` / `country`

This is a one-time pass.

## Planned Changes Summary

- Input attribute audit pass across `resources/js/pages/**/*.vue` and `resources/js/components/**/*.vue` based on the table above.
- Verify `toDateTimeLocal()` and any sibling helpers in `Trips/Show.vue` emit values that don't break the iOS native picker.
- Documents-tab drop zone — touch-first variant on `<sm`.
- Reservation-card attachment picker — same pattern.
- Confirm `accept=` attributes on file inputs cover both extensions and MIME types.

## State Management

- No new state owners. All changes are markup/attribute level.
- File staging state stays where it is (the existing `useForm` per upload surface).

## Tests

This phase is markup. Rely on:

- Phase 06 real-device matrix (the only authoritative test for native keyboard behavior).
- `npm run types:check`, `npm run lint:check`, and `npm run build` as the required gates.
- An optional Pest browser smoke `tests/Browser/MobileFilePickerTest.php`:
  - At 390×844, open the documents tab. Assert the dashed-border / drag-and-drop UI is hidden.
  - Use Playwright's `setInputFiles` to stage two files. Assert two filename rows appear below the button.
  - Click Remove on one row. Assert the row disappears and the file is removed from the staged list.
  - Verify reservation-card attachment picker behaves the same way.

## Acceptance Criteria

- Every numeric / decimal input shows the correct virtual keyboard on iOS Safari and Android Chrome (decimal pad for fees, numeric keypad for quantities, telephone keypad for phone numbers).
- Every date / time input opens the native wheel picker on iOS Safari without a tap-twice bug.
- Search box on `/trips/search` shows the keyboard with a Search button on Enter.
- The documents-tab and reservation-card file pickers both, on `<sm`, hide drag-and-drop UI and surface only a styled button + clear staged-file list.
- File pickers show Camera / Photo Library / Browse on iOS via the `accept` attribute.
- Autofill works for email, name, phone, and address fields where present.
- All audit findings tagged `inputs | keyboard` are addressed.
- `npm run lint:check`, `npm run types:check`, and `npm run build` pass.

## Out Of Scope

- Building or adopting a custom date / time picker library.
- Currency typeahead with full ISO 4217 dropdown (the 3-letter `<input>` is fine).
- Phone number formatting / validation library.
- Address autocomplete via Google Places or similar.
- Drag-and-drop multi-file upload from non-traditional sources (cloud drives) — relies on browser support that isn't worth chasing.
- Replacing the existing useForm with a different form library.
