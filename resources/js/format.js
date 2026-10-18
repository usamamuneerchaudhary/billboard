export function formatNumber(value) {
    return new Intl.NumberFormat().format(value ?? 0);
}

export function formatPercent(value) {
    return `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value ?? 0)}%`;
}

/**
 * Relative change between two numbers, or null when there's nothing to compare.
 */
export function change(current, previous) {
    if (!previous) return null;

    return ((current - previous) / previous) * 100;
}
