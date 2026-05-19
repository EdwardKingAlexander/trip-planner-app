# Phase 01 - Mobile Audit And Pain-Point Inventory

## Goal

Produce a concrete, viewport-keyed list of every mobile pain point on the live app. No code changes. Output is a structured JSON inventory the rest of the module works to. Without this, Phases 02–05 risk shipping fixes that the user never asked for and missing the ones they did.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: none
- Blocker: none

## Audit Inputs

- Live Herd URL (`http://vacation-plan-app.test`) at four viewports: 360 × 640, 390 × 844 (iPhone 14), 430 × 932 (iPhone 14 Pro Max), and 820 × 1180 (iPad Mini portrait — the boundary case).
- Pages to walk: `/login`, `/trips`, `/trips/{id}` for a populated trip (every panel: itinerary, reservations, costs, packing, tasks, documents, reminders, sharing), `/trips/search`, `/calendar`, `/reminders`, `/notifications`, `/settings/*`.
- Browser tools: Chrome DevTools device toolbar for repeatable widths plus Safari Responsive Design Mode (the iOS Safari behavior is what users actually hit).
- Existing planning docs already shipped: `responsive-ui-uplift.md`, `scroll-issue/`, `navigation-uplift/`. Treat their fixes as baseline; everything they covered is *not* in scope here.

## Audit Method

For each page × viewport combination, the auditor records:

| Field | Notes |
| --- | --- |
| `page` | Route path, e.g. `/trips/{id}#packing` |
| `viewport` | `360x640`, `390x844`, `430x932`, or `820x1180` |
| `category` | One of: `shell`, `nav`, `forms`, `inputs`, `lists`, `feedback`, `lightbox`, `safe-area`, `density`, `gesture`, `keyboard`, `loading`, `toasts`, `other` |
| `severity` | `critical` (blocks task), `high` (annoys every visit), `medium` (annoys sometimes), `low` (nice to have) |
| `pain_point` | One sentence — what's wrong |
| `evidence` | Screenshot filename in `ai/audit/mobile-2026-05-14/`, plus the relevant component/file:line if known |
| `proposed_fix_phase` | Which downstream phase (02 / 03 / 04 / 05) is the right home |
| `notes` | Anything else worth saying |

The findings live in `ai/state/mobile-ui-ux.json` under `audit_findings`.

## Mandatory Walks

Each walk produces 5–15 findings. The auditor does not need to find brand-new categories — many pain points are predictable. Phase 01's job is to *confirm and document* them so the fix phases have authority.

### Walk 1: First-launch shell

- Sign out, sign in. Watch for:
  - Layout shift between `<head>` paint and Vue hydration on mobile Safari.
  - Sticky header height vs. content `padding-top`.
  - Status bar / notch overlap on a notched simulator.
  - The mobile sheet trigger position (does it sit under the safe area, is it reachable one-handed).
  - Toast / flash positioning after sign-in success.

### Walk 2: Trips index and trip create

- `/trips`. Note:
  - Card list density at 360 px.
  - "Create trip" CTA reachability.
  - The trip create form: every input one column, date inputs use the OS picker, submit button reachable without scroll.

### Walk 3: Trip workspace — every panel

- `/trips/{id}`. Walk every tab. For each:
  - Above-the-fold content: how much before the user has to scroll.
  - Edit form behavior: what happens when the user taps Edit on a row.
  - Form length: how many scrolls to reach Save on the longest form (likely the reservation edit, especially with the new `flight_details` block).
  - Row actions: how easy it is to mistakenly hit Delete vs Edit.
  - The drop zone in Documents: does it look like it expects a mouse.
  - The reservation card's collapsible Attachments + Flight details details: are they touch-friendly.
  - The lightbox: tap-to-close hit area, close-button position, safe-area bottom.
  - The packing checkbox: tap target size, optimistic feedback timing.

### Walk 4: Notifications and reminders

- `/notifications`, `/reminders`, the bell dropdown:
  - Notification rows: tap target, swipe-to-dismiss feasibility.
  - Bell dropdown positioning on mobile (does it open as a sheet or as a tiny dropdown).
  - Empty states.

### Walk 5: Search and settings

- `/trips/search`:
  - Search input: keyboard type, Enter behavior, clear button.
  - Result list density.
- `/settings/*`:
  - Form lengths, field grouping.
  - Theme picker on mobile.

### Walk 6: Long forms specifically

- Reservation edit (with the new flight-details `<details>` block):
  - Total field count visible.
  - How many viewports tall.
  - What happens when validation fails three sections up.
- Document upload:
  - Drop zone vs. button affordance.
  - Multi-file selection from the OS file picker.
  - Title prefix + metadata fields.
- Trip create / edit:
  - Date pickers.

### Walk 7: Edge cases

- Landscape orientation on phone.
- Software keyboard up: does the focused input stay visible.
- Slow 3G throttle: page transition feedback.
- iOS Safari double-tap zoom: does it happen on any tap target.

## Output Shape (frozen)

The audit JSON section in `ai/state/mobile-ui-ux.json`:

```json
"audit_findings": [
    {
        "id": "mob-001",
        "page": "/trips/{id}",
        "viewport": "390x844",
        "category": "forms",
        "severity": "high",
        "pain_point": "Reservation Edit form opens inline and pushes the row offscreen; with the flight_details <details> block expanded, the form is ~5 viewports tall.",
        "evidence": "ai/audit/mobile-2026-05-14/trips-show-edit-reservation-390.png; resources/js/pages/Trips/Show.vue:832-925",
        "proposed_fix_phase": "03",
        "notes": "Replace with full-screen Sheet at <sm; keep inline at >=sm."
    }
]
```

Phase 02–05 owners then filter `audit_findings` by `proposed_fix_phase` to know exactly what they have to address.

## Severity Triage Rules

- `critical` — the user can't complete a primary task at all. Examples: a button is unreachable behind the safe area; a modal close button is offscreen; the keyboard hides the input.
- `high` — the user can complete the task but it actively annoys them every time. Examples: inline form push, datetime input with bad keyboard, lightbox close hard to hit.
- `medium` — annoying once you notice. Examples: sticky header eats too much space; toast at top is hard to dismiss.
- `low` — polish. Examples: tap-target visual padding; transition timing.

Phase 06 verification asserts every `critical` finding is fixed. `high` should also all be fixed; `medium` are stretch; `low` are deferred to a follow-up.

## State Management

Audit output is the only artifact. No code changes. No new state owners.

## Acceptance Criteria

- `audit_findings` array in `ai/state/mobile-ui-ux.json` contains at least 30 findings spanning every mandatory walk.
- Every finding has all required fields populated.
- Every finding maps to one of phases 02 / 03 / 04 / 05 (or is explicitly marked `out-of-scope` with a reason).
- Screenshots are saved under `ai/audit/mobile-2026-05-14/` with filenames matching the `evidence` field.
- A short "audit summary" paragraph at the top of `audit_findings` lists the top 3 themes (e.g. "inline forms dominate complaints", "safe-area never respected", "no row-level touch action").

## Out Of Scope

- Implementing fixes (Phases 02–05).
- Visual regression / screenshot diffing tooling.
- Automated audit via Lighthouse — Lighthouse mobile score is a Phase 06 gate, not an audit method.
- A11y audit (separate effort if needed; this module is mobile UX).
- Performance benchmarking beyond "does it feel slow on a slow connection."
