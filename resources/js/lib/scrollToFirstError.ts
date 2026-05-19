export function scrollToFirstError(errors: Record<string, string>, container?: HTMLElement | null): void {
    const firstKey = Object.keys(errors)[0];

    if (!firstKey) {
        return;
    }

    const root = container ?? document;
    const escapedKey = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(firstKey) : firstKey.replace(/"/g, '\\"');
    const target = root.querySelector<HTMLElement>(`[name="${escapedKey}"], [data-error-target="${escapedKey}"]`);

    if (!target) {
        return;
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.focus({ preventScroll: true });
}
