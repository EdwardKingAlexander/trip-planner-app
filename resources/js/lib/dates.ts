const dateOnlyPattern = /^(\d{4})-(\d{2})-(\d{2})$/;

export function formatTripDate(
    value: string | null | undefined,
    timeZone: string | null | undefined = 'UTC',
    options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' },
): string {
    if (!value) {
        return 'Flexible';
    }

    const match = dateOnlyPattern.exec(value);

    if (match) {
        const [, year, month, day] = match;
        const utcMidnight = Date.UTC(Number(year), Number(month) - 1, Number(day));

        return new Intl.DateTimeFormat(undefined, { ...options, timeZone: 'UTC' }).format(new Date(utcMidnight));
    }

    return new Intl.DateTimeFormat(undefined, { ...options, timeZone: timeZone || undefined }).format(new Date(value));
}

export function formatTripDateTime(
    value: string | null | undefined,
    timeZone?: string | null,
    options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' },
): string {
    if (!value) {
        return 'Time TBD';
    }

    return new Intl.DateTimeFormat(undefined, { ...options, timeZone: timeZone || undefined }).format(new Date(value));
}

export function timezoneLabel(timeZone: string | null | undefined): string {
    if (!timeZone) {
        return 'UTC';
    }

    const now = new Date();
    const short = new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'short' })
        .formatToParts(now)
        .find((part) => part.type === 'timeZoneName')?.value;
    const offset = new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'shortOffset' })
        .formatToParts(now)
        .find((part) => part.type === 'timeZoneName')?.value;
    const normalizedOffset = offset === 'GMT' ? 'GMT+0' : offset;

    return [short, normalizedOffset].filter(Boolean).join(', ') || timeZone;
}
