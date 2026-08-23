/** Formatting helpers shared by the tables and detail pages. */

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'medium' });
}

export function formatNumber(
    value: number | string | null | undefined,
    fractionDigits = 2,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numeric = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(numeric)) {
        return '—';
    }

    return numeric.toLocaleString('id-ID', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}
