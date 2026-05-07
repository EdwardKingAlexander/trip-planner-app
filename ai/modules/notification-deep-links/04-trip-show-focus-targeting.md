# Phase 04 - Trip Show Focus Targeting

## Goal

Make `/trips/{id}?focus=<panel>&from=notification#<anchor>` actually do something on arrival: open the right tab, scroll the row into view, and pulse-highlight it briefly so the user can see what changed.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phases 01-03
- Blocker: none

## Planned Changes

### `resources/js/pages/Trips/Show.vue`

#### Honor `focus` query param on mount

- Read `usePage().url` (or `window.location`) once on mount. Parse `focus`, `from`, and `missing` from the query string.
- If `focus` matches a known panel id, set `activePanel` to that value before paint. Use the existing `activePanel` ref — no new state machine.
- If `focus` is absent, fall back to today's default panel.

#### Honor URL fragment for scroll target

- After Vue mounts and the focused panel renders, find the element by `id="<anchor>"`.
- `scrollIntoView({ behavior: 'smooth', block: 'center' })`.
- Wait one tick before scrolling so deferred props / collapsible sections have time to render.
- If the element is missing (deleted between notification creation and click), do nothing — the `&missing=1` toast covers the messaging.

#### Pulse-highlight when arriving from a notification

- Only when `from=notification` is in the URL.
- Add a `data-focus-pulse="true"` attribute to the targeted element for ~1200ms, then remove it.
- New CSS in `resources/css/app.css` under `@layer components`:
  ```css
  [data-focus-pulse='true'] {
      animation: focus-pulse 1200ms ease-out 1;
      animation-fill-mode: both;
  }
  @keyframes focus-pulse {
      0%   { box-shadow: 0 0 0 0 hsl(var(--ring) / 0.0); }
      30%  { box-shadow: 0 0 0 6px hsl(var(--ring) / 0.35); }
      100% { box-shadow: 0 0 0 0 hsl(var(--ring) / 0.0); }
  }
  ```
- Uses theme tokens so it adapts to whatever theme the user has (`themes-plan` module compatible).

#### Toast on missing subject

- If `missing=1` is in the URL, render an inline alert at the top of the panel: "That item is no longer available." Auto-dismiss after 5 seconds or on first interaction.
- Use the existing `Alert` component family (`@/components/ui/alert`) — do not introduce a new toast library.

### Anchor IDs on existing rows

Every panel must render its rows with the contract anchor IDs from Phase 01. Audit each panel:

- `itinerary` — itinerary-item rows need `:id="\`itinerary-item-${item.id}\`"`.
- `reservations` — `:id="\`reservation-${reservation.id}\`"`.
- `budget` — `:id="\`cost-${cost.id}\`"`.
- `packing` — `:id="\`packing-item-${item.id}\`"`. (Note: `packing-checkbox` module also touches this list — coordinate to avoid conflicts.)
- `tasks` — `:id="\`task-${task.id}\`"`.
- `documents` — `:id="\`document-${document.id}\`"`.
- `reminders` — `:id="\`reminder-${reminder.id}\`"`.
- `sharing` — `:id="\`collaborator-${collaborator.id}\`"`.

Each addition is one attribute on the row container — no structural changes.

### Tests

- Browser smoke (Pest, in Phase 05):
  - Visit `/trips/12?focus=packing&from=notification#packing-item-87`.
  - Assert Packing tab is the active panel.
  - Assert `#packing-item-87` is in the viewport.
  - Assert it carries `data-focus-pulse` for at least 200ms after mount, then loses it before 2000ms.

### Accessibility

- The pulse is a visual cue only. Also call `element.focus()` on the targeted row's first interactive child (or the row itself if `tabindex="-1"` is set) so screen readers and keyboard users land on the changed item.
- Do not steal focus if the user has already started typing in a form on the page.

## State Management

- All new state is local to `Trips/Show.vue`: `activePanel` (already exists), a one-shot ref tracking pulse timeout id.
- No global stores. No persistence — the focus is an arrival behavior, not a saved view.
- The query params are the source of truth for arrival; clearing them is optional and not required (next manual click on a tab won't be confused since `activePanel` is bound to user clicks too).

## Acceptance Criteria

- Arriving at `/trips/{id}?focus=packing&from=notification#packing-item-87` opens the Packing tab, scrolls the row into view, pulses it, and focuses it.
- Arriving without `focus` behaves identically to today (no regression).
- Arriving with `&missing=1` shows the missing-item alert and the panel still renders.
- Theme-token pulse looks consistent across all five themes (`themes-plan/03-five-premade-themes.md`).
- `npm run lint:check` and `npm run types:check` pass.

## Out Of Scope

- Animated tab transitions.
- Persistent "you have unread changes" badges per panel.
- Re-scrolling on subsequent realtime updates (handled by `live-trip-collaboration`).
