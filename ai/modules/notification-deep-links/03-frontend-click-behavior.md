# Phase 03 - Frontend Click Behavior

## Goal

Make every notification click — bell dropdown or inbox page — navigate to the resolved deep link. No more trip-only redirects.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 02 (resolver + endpoint shipped)
- Blocker: none

## Planned Changes

### `resources/js/types/index.ts` (or wherever `TripNotification` lives)

- Add `subject_type: string | null` and `subject_id: number | string | null`.
- Add `deep_link: string` (always present after Phase 02; never null).
- Keep existing fields (`trip_id`, `summary`, `changed_area`, etc.).

### `resources/js/components/NotificationBell.vue`

- Replace the body of `openNotification` (currently lines 52-66) with a single Wayfinder-typed call:
  ```ts
  import { go as notificationGo } from '@/routes/notifications';
  ...
  const openNotification = (notification: TripNotification): void => {
      router.visit(notificationGo(notification.id).url);
  };
  ```
- The `go` endpoint already marks read AND redirects, so no chained `POST /read` is needed.
- Remove the now-unused `tripShow` import if it was only used here.
- The unread badge update happens automatically when the next page response refreshes the shared `notifications` prop — no manual `decrement` needed.

### `resources/js/pages/notifications/Index.vue`

- Change the `<Link>` `href` to `notification.deep_link` (relative URL string from the serializer).
- Inertia's `<Link>` handles relative URLs correctly; `router.visit` is not required.
- Drop the `tripShow` import if unused after the change.

### Behavioral notes

- **Middle-click / Cmd-click compatibility:** because `<Link>` with a real `href` is used (not a pure JS click handler), the user can open a deep link in a new tab and it still resolves through `/notifications/{id}/go`, which marks read and redirects. This is intentional.
- **Optimistic read marker:** when clicking, mark the notification's `read_at` locally (in the bell's recent list) before the navigation completes so the dot disappears immediately. Reconcile on next response.

### Tests

- Browser-level smoke: from `/trips`, open the bell, click a notification, assert the URL becomes `/trips/{id}?focus=<panel>&from=notification#<anchor>` and no console errors. (Lives in Phase 05's browser test alongside the rest of the verification matrix.)

## State Management

- Notification list state continues to live in `usePage().props.notifications` (shared from the backend, refreshed on each Inertia response).
- The optimistic `read_at` flip is a tiny local mutation on a shallow-cloned list; do not introduce a Pinia/Vuex store for this.
- No new global stores.

## Acceptance Criteria

- A grep over `resources/js/components/NotificationBell.vue` and `resources/js/pages/notifications/Index.vue` shows no remaining hard-coded `/trips/${tripId}` paths for notification handling.
- `notification.deep_link` is consumed in both the bell and the inbox.
- Middle-clicking a notification opens the resolved deep link in a new tab.
- `npm run lint:check` and `npm run types:check` pass.

## Out Of Scope

- Tab selection and pulse-highlight on the destination page (Phase 04).
- Notification grouping / threading.
- Inline previews inside the bell dropdown.
