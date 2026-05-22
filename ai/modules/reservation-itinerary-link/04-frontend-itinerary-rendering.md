# Phase 04 - Itinerary Tab Rendering

## Goal

Render `day.reservations` under each day card on the itinerary tab as read-only entries with a visible "Reservation" badge, a type icon, the time + provider line, and an "Open" action that deep-links to the matching card on the Reservations tab. The user sees the reservation on every day it covers; clicking through edits it once on the Reservations tab.

## Status

- Status: `verified`
- Owner: codex
- Depends on: Phase 03
- Blocker: none

## Audit Inputs

- `resources/js/pages/Trips/Show.vue:1223-1286` — current `day.items` render block. New reservation block sits immediately after this, before `day.tasks`.
- `resources/js/pages/Trips/Show.vue:1287-1310+` — current `day.tasks` render block. Position-of-insert reference.
- `resources/js/pages/Trips/Show.vue:1594+` — current Reservations tab card. The deep-link target.
- `app/Services/NotificationDeepLinkResolver.php` — existing focus-target convention. Reused for the itinerary→reservation jump.
- `resources/js/pages/Trips/Show.vue:171-188` — existing tab state ref (likely `activeTab` or similar). Audit to confirm name; the "Open" handler switches the tab to `'reservations'` then focuses.

## Per-Day Reservation Card (frozen markup)

Inserted between the closing `</div>` of the `day.items` `v-for` block and the opening of the `day.tasks` `v-for` block:

```html
<div
  v-for="reservation in day.reservations"
  :id="`itinerary-day-${day.id}-reservation-${reservation.id}`"
  :key="`itinerary-day-${day.id}-reservation-${reservation.id}`"
  class="rounded-md border border-dashed border-border bg-muted/30 p-3 dark:border-border dark:bg-muted/20"
>
  <div class="flex items-start justify-between gap-3">
    <div class="min-w-0">
      <div class="flex items-center gap-2 text-sm font-semibold">
        <component :is="reservationTypeIcon(reservation.type)" class="h-4 w-4 text-muted-foreground" />
        <span class="truncate">{{ reservation.title }}</span>
        <span v-if="reservation.spans_multiple_days" class="text-xs font-normal text-muted-foreground">
          · {{ reservation.type === 'lodging' ? 'Night' : 'Day' }} {{ reservation.day_index }} of {{ reservation.day_total }}
        </span>
      </div>
      <div class="mt-1 text-xs text-muted-foreground">
        <span class="rounded-full bg-accent px-2 py-0.5">Reservation</span>
        <span class="ml-2">{{ reservation.type }}</span>
        <span v-if="reservation.starts_at" class="ml-2">· {{ formatDateTime(reservation.starts_at, reservation.starts_timezone) }}</span>
        <span v-if="reservation.provider_name" class="ml-2">· {{ reservation.provider_name }}</span>
      </div>
      <p v-if="reservation.location_name" class="mt-2 text-sm text-muted-foreground">{{ reservation.location_name }}</p>
    </div>
    <Button size="sm" type="button" variant="outline" class="travel-touch shrink-0" @click="openReservation(reservation.id)">
      Open
    </Button>
  </div>
</div>
```

Visual distinction from free-form `day.items`:

- Dashed border (vs. solid for items) — signals "not editable here".
- Subtle muted background tint.
- Always-visible "Reservation" badge.
- No Edit / Delete buttons. Only "Open".

## `reservationTypeIcon()` Helper (frozen)

A small map in `Trips/Show.vue` that returns the Lucide icon component for each reservation type:

```ts
import { Plane, BedDouble, Car, Ticket, Utensils, CalendarClock } from 'lucide-vue-next';

function reservationTypeIcon(type: string) {
  switch (type) {
    case 'flight': return Plane;
    case 'lodging': return BedDouble;
    case 'transport': return Car;
    case 'activity': return Ticket;
    case 'dining': return Utensils;
    default: return CalendarClock;
  }
}
```

Reuses Lucide (already a dependency via shadcn-vue components).

## `openReservation()` Handler (frozen)

```ts
function openReservation(reservationId: number) {
  activeTab.value = 'reservations';

  nextTick(() => {
    const el = document.getElementById(`reservation-${reservationId}`);
    if (el === null) {
      return;
    }
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.focus({ preventScroll: true });
  });
}
```

Requires that the Reservations tab's existing card markup carries an `id="reservation-{id}"` attribute and a `tabindex="-1"` (audit the existing Reservations tab block — if missing, add). The pattern matches `notification-deep-links`.

## Empty-State Behavior

- A day with zero items, zero tasks, and zero reservations: the existing empty-state message at `Trips/Show.vue` (the `v-if="day.items.length || day.tasks.length"` guard) is extended to `v-if="day.items.length || day.tasks.length || day.reservations.length"`. When **all three** are empty, the "Nothing planned" placeholder shows. When any one has content, the placeholder hides.
- A day with only reservations renders the reservation cards in the same container, no empty-state message.

## Shared-Trip Authorship

Reservation cards on the itinerary tab do **not** show "Last edited by …". The full Reservations tab card already exposes that attribution. Keeping the day-card terse reinforces "this is a glance, click Open for detail".

## Audit-Before-Write Items

Before implementing:

- Confirm the tab-state ref name in `Trips/Show.vue` (likely `activeTab` — record the actual name in state).
- Confirm the Reservations tab card has `id="reservation-{id}"` and `tabindex="-1"`. If not, add both as part of this phase (one-line change to that block).
- Confirm `formatDateTime` is already imported and in scope on the itinerary template (it is at line 1594; verify it's also in scope above).

## Deliverables

- `resources/js/pages/Trips/Show.vue` — new reservation render block in the itinerary day card, `reservationTypeIcon()` helper, `openReservation()` handler, updated empty-state guard.
- Reservations tab card: confirm or add `id="reservation-{id}"` and `tabindex="-1"`.
- Optional: a tiny new browser test (`tests/Browser/ItineraryReservationDeepLinkTest.php`) verifying click-through navigation works.

## Acceptance Criteria

- A trip with a 3-night lodging reservation shows the reservation entry on the check-in day card and the two middle days, but **not** the check-out day card. Each entry shows "Night 1 of 3", "Night 2 of 3", "Night 3 of 3" respectively.
- A trip with two flights on the same day shows both flight entries on that day, ordered by `starts_at`.
- An unscheduled reservation (`starts_at = null`) appears in the Reservations tab and on **zero** itinerary day cards.
- Clicking "Open" on any itinerary reservation entry switches to the Reservations tab and scrolls the matching card into view with a brief focus ring.
- An empty day (no items, no tasks, no reservations) still shows the "Nothing planned" empty state. A day with only a reservation does **not** show the empty state.
- Free-form `ItineraryItem` rendering, editing, and deletion are unchanged.
- `npm run types:check`, `npm run lint:check`, and `npm run build` all pass.

## Risks

- Visual confusion if reservation entries look too similar to free-form items. Mitigated by dashed border + muted bg + "Reservation" badge.
- Tab-switch jump can be jarring on mobile. The smooth-scroll behavior matches the `notification-deep-links` pattern that already shipped; if it's a problem there, it'll surface here too — fixed once, both benefit.

## Out Of Scope

- Inline editing of reservations from the itinerary tab.
- Drag-and-drop reordering.
- Interleaved sorting of items + reservations by `starts_at` within a day. They render as two grouped blocks (items first, then reservations) — interleaving is a follow-up.
- Mobile-specific layout variations (the existing card pattern already handles mobile via container queries).
