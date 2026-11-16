/**
 * Normalize a day selection payload from the backend into a sorted, unique list
 * of day-of-month integers (`1..31`), defending against legacy associative maps
 * (`{ "4": true }`, `{ "4": "D" }`, `{ "1": { rencana: true, realisasi: true } }`).
 */
export function toDayList(value: unknown): number[] {
    if (value == null) {
        return [];
    }

    const days = new Set<number>();

    if (Array.isArray(value)) {
        for (const item of value) {
            const day = Number(item);
            if (Number.isInteger(day) && day >= 1 && day <= 31) {
                days.add(day);
            }
        }
        return Array.from(days).sort((a, b) => a - b);
    }

    if (typeof value === 'object') {
        for (const [key, entry] of Object.entries(value as Record<string, unknown>)) {
            const day = Number(key);
            if (!Number.isInteger(day) || day < 1 || day > 31) {
                continue;
            }
            if (entry === true || entry === 1) {
                days.add(day);
            } else if (typeof entry === 'string' && entry.trim() !== '') {
                days.add(day);
            } else if (entry && typeof entry === 'object') {
                const obj = entry as Record<string, unknown>;
                if (Boolean(obj.realisasi || obj.rencana)) {
                    days.add(day);
                }
            }
        }
        return Array.from(days).sort((a, b) => a - b);
    }

    return [];
}

/**
 * Ensure `rencana` and `realisasi` fields on every row are clean `number[]` day lists.
 */
export function normalizeRencRealRows<T extends { rencana: unknown; realisasi: unknown }>(
    rows: T[],
): Array<Omit<T, 'rencana' | 'realisasi'> & { rencana: number[]; realisasi: number[] }> {
    return rows.map((row) => ({
        ...row,
        rencana: toDayList(row.rencana),
        realisasi: toDayList(row.realisasi),
    }));
}

/**
 * Ensure `jadwal` fields on every row are clean `number[]` day lists.
 */
export function normalizeJadwalRows<T extends { jadwal: unknown }>(
    rows: T[],
): Array<Omit<T, 'jadwal'> & { jadwal: number[] }> {
    return rows.map((row) => ({
        ...row,
        jadwal: toDayList(row.jadwal),
    }));
}
