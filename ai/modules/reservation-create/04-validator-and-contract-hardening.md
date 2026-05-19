# Phase 04 - Validator And Contract Hardening

## Goal

Close the remaining server-side and protocol-layer gaps that can still produce "nothing happens" after Phases 02 and 03:

1. Make `after_or_equal:starts_at` timezone-aware so a cross-tz flight cannot fail spuriously.
2. Confirm `ConvertEmptyStringsToNull` is on (or fix the code paths that assume it is).
3. Surface `419 Page Expired` (session timeout) as a clear toast — without it, a stale tab still feels like "nothing happens."
4. Verify the controller's redirect-on-failure path correctly preserves `form.errors` (it should — Inertia handles this — but a regression test locks it in).
5. Tighten `flight_details` validation so its silent-422 paths are also caught by the Phase 02 surface.

## Status

- Status: `planned`
- Owner: unassigned
- Depends on: Phase 03 (the picker is the input side of the timezone-aware comparison)
- Blocker: none

## Sub-task 1: `EndsAtAfterStarts` rule

`'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']` compares Carbon-parsed naive datetimes — wall-clock against wall-clock, ignoring `starts_timezone` and `ends_timezone`. For a flight that departs LAX on 2026-09-26T22:00 PDT and arrives MNL on 2026-09-28T06:00 PHT, wall-clock comparison says "Sep 28 06:00" > "Sep 26 22:00" and passes — correct by luck. But for a 2-hour redeye that crosses the date line in the opposite direction, the wall-clock comparison can disagree with the real instant comparison.

Replace with `app/Rules/EndsAtAfterStarts.php`:

```php
final class EndsAtAfterStarts implements ValidationRule
{
    public function __construct(
        private readonly ?string $startsAt,
        private readonly ?string $startsTimezone,
        private readonly ?string $endsTimezone,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->startsAt || ! $value || ! $this->startsTimezone || ! $this->endsTimezone) {
            return;
        }

        try {
            $start = Carbon::parse($this->startsAt, $this->startsTimezone);
            $end = Carbon::parse($value, $this->endsTimezone);
        } catch (\Throwable) {
            $fail('The end date and time could not be parsed.');
            return;
        }

        if ($end->lt($start)) {
            $fail('The end date and time must be at or after the start date and time.');
        }
    }
}
```

Used in `validatedReservation`:

```php
'ends_at' => [
    'nullable',
    'date',
    new EndsAtAfterStarts(
        $request->input('starts_at'),
        $request->input('starts_timezone'),
        $request->input('ends_timezone'),
    ),
],
```

The rule runs only when all four inputs are present. If any is missing, validation falls through to the basic `date` rule.

## Sub-task 2: `ConvertEmptyStringsToNull`

Confirm in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->convertEmptyStringsToNull();
})
```

Laravel 13's default is on; verify nothing has removed it. If it's off, decide:

- (A) Turn it on globally — simplest, but a behavior change for every controller.
- (B) Coerce empty strings to null inside `validatedReservation` for the specific fields that are `nullable, string, max:N` — narrower blast radius.

Default to (A). Document the decision in the state file.

## Sub-task 3: 419 surface

`resources/js/app.ts` (or wherever Inertia is bootstrapped) — add:

```ts
router.on('exception', (event) => {
    const status = (event.detail as { exception?: { response?: { status?: number } } }).exception?.response?.status;
    if (status === 419) {
        toast.error('Your session expired — refresh and try again.', { duration: 8000 });
        event.preventDefault();
    }
});
```

This runs once per app, not per form. Single toast for any stale-tab submit.

If the Inertia v3 event surface doesn't expose the status this way (the `exception` event renamed from `invalid` in v3 — double-check the payload shape during Phase 04), the fallback is a tiny global Axios-style interceptor on the underlying XHR client.

## Sub-task 4: Lock in the redirect-on-failure contract

The controller already returns `back()->with('success', 'Reservation added.')` on success. On failure (Laravel auto-handling), Inertia's behavior is:

- 422 → Inertia populates `form.errors`; no navigation occurs.
- 419 → Inertia handles via the exception event (covered above).
- 500 → Inertia's networkError event fires.

Lock with feature tests in Phase 05:

- Assert that POST with bad data returns 302 → `back()` with `errors` keyed on the failed field (Laravel's automatic behavior, but easy to break if a future change introduces a custom response).
- Assert that POST with good data returns 302 → `back()` with `flash.success`.

## Sub-task 5: `flight_details` validation

The `flight_details.*` rules (TripReservationController:107-125) all fail silently today because no `InputError` exists for them on the edit form (or anywhere; the add form doesn't include them).

Phase 02 already wires `InputError` for `flight_details.cabin_class`, `flight_details.currency`, etc. on the edit form. Phase 04's job is server-side polish:

- Confirm `decimal:0,2` accepts integers (`5` → `'5'` → fails `decimal:0,2`? Test it.). If it rejects integers, switch to `'numeric', 'regex:/^\d+(\.\d{1,2})?$/'`.
- Confirm `regex:/^[A-Z]{3}$/` on currency rejects lowercase. Decide whether to uppercase server-side (better UX) or keep strict (better data hygiene). Recommend uppercasing server-side — currencies are case-insensitive in user mental models.

```php
'flight_details.currency' => ['nullable', 'string', 'size:3'],
// then after validation:
if (isset($validated['flight_details']['currency'])) {
    $validated['flight_details']['currency'] = strtoupper($validated['flight_details']['currency']);
}
```

## State Management

- New rule class, no DB changes, no new tables.
- 419 interceptor is global, fires at most once per session expiry.
- No new front-end state owners.

## Tests

`tests/Feature/Trips/ReservationCrossTimezoneTest.php`:

- `it accepts a flight that crosses the dateline forward` — LAX→MNL, departure 2026-09-26T22:00 PDT, arrival 2026-09-28T06:00 PHT. Assert 302 success.
- `it rejects an end instant earlier than the start instant in real time` — submit `starts_at = 2026-09-26T22:00 / Asia/Tokyo`, `ends_at = 2026-09-26T05:00 / America/Los_Angeles` (the LA timestamp is *later* in UTC, so this should pass). Inverted case: submit fields where LA is earlier than Tokyo in UTC — assert 422.
- `it tolerates equal start and end instants in different timezones` — submit `starts_at = 2026-09-26T22:00 / America/Los_Angeles`, `ends_at = 2026-09-27T14:00 / Asia/Tokyo` (same UTC moment, +1h). Assert 302 success.

`tests/Feature/Trips/ReservationValidatorContractTest.php`:

- `it returns 302 back with errors on 422` — POST bad data, assert response code 302 and `errors.starts_timezone` in session.
- `it returns 302 back with success on happy create` — POST good data, assert response code 302 and `session.flash.success`.
- `it uppercases flight_details.currency on save` — POST `flight_details.currency = 'usd'`, assert stored value is `'USD'`.

Vitest:

- 419 toast renders when the Inertia exception event fires with status 419. Stubbed event.

## Acceptance Criteria

- `EndsAtAfterStarts` rule replaces `after_or_equal:starts_at` for reservations.
- The cross-tz LAX→MNL example saves cleanly. The inverted-UTC reverse case fails with a visible error.
- `ConvertEmptyStringsToNull` confirmed on (or narrower coercion documented if off).
- 419 produces a toast. Verified via a Pest 4 browser test (or manual + Vitest if Pest can't simulate 419 cleanly).
- `flight_details.currency` uppercased on save.
- `vendor/bin/pint --dirty --format agent` clean.
- `php artisan test --compact --filter='ReservationCrossTimezone|ReservationValidatorContract'` green.

## Out Of Scope

- Migrating the `*_timezone` columns to a different storage strategy (they stay as strings).
- Per-leg timezone-aware comparisons on itinerary items (different module).
- Cross-form 419 handling beyond the global toast (covers all forms uniformly).
- Auto-refreshing the page on 419 (intentional choice — let the user save anything they've typed before the refresh).
- A `decimal:0,2` polyfill if the audit shows the rule does accept integers.
