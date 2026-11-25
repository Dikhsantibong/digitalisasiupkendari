/**
 * The logsheet table head of a template (jenis mesin): up to three header
 * levels (group_label → name → sub_channel), consecutive equal labels merged
 * like the paper form, a parameter with fewer levels spanning the remaining
 * rows. Mirrors App\Services\Operator\LogsheetLayout — keep the two in sync.
 */

export type LayoutParameter = {
    group_label?: string | null;
    name: string;
    sub_channel: string | null;
    normal_value?: string | null;
    alarm_value?: string | null;
    trip_value?: string | null;
};

export type HeadCell = { label: string; colspan: number; rowspan: number };

export function headerRows(parameters: LayoutParameter[]): HeadCell[][] {
    const paths = parameters.map((p) =>
        [p.group_label, p.name, p.sub_channel].filter(
            (label): label is string =>
                label !== null && label !== undefined && label !== '',
        ),
    );
    const depth = Math.max(1, ...paths.map((path) => path.length));
    const rows: HeadCell[][] = Array.from({ length: depth }, () => []);

    for (let level = 0; level < depth; level++) {
        let previousKey: string | null = null;

        for (const path of paths) {
            // The first label takes the rows the path lacks; the rest one row each.
            const extra = depth - path.length;
            const index = level === 0 ? 0 : level - extra;

            if (
                index < 0 ||
                index >= path.length ||
                (level > 0 && index === 0)
            ) {
                previousKey = null;
                continue;
            }

            const key = JSON.stringify(path.slice(0, index + 1));
            const row = rows[level];

            if (key === previousKey) {
                row[row.length - 1].colspan++;
                continue;
            }

            row.push({
                label: path[index],
                colspan: 1,
                rowspan: index === 0 ? extra + 1 : 1,
            });
            previousKey = key;
        }
    }

    return rows.filter((row) => row.length > 0);
}

export type ReferenceRow = {
    label: string;
    field: 'normal_value' | 'alarm_value' | 'trip_value';
};

/** The reference rows printed under the head (only those the template fills). */
export function referenceRows(parameters: LayoutParameter[]): ReferenceRow[] {
    const all: ReferenceRow[] = [
        { label: 'Normal', field: 'normal_value' },
        { label: 'Min / Alarm', field: 'alarm_value' },
        { label: 'Max / Trip', field: 'trip_value' },
    ];

    return all.filter((row) =>
        parameters.some((p) => (p[row.field] ?? '') !== ''),
    );
}

/** A reference text at least this long is merged over its neighbours (paper form). */
const MERGE_REFERENCE_MIN_LENGTH = 15;

export type ReferenceCell = { value: string; colspan: number };

/**
 * The cells of one reference row: consecutive parameters of the same group
 * sharing one long note (e.g. the Exhaust "Pada beban …" text, "Pembacaan
 * Langsung") are merged; short values stay one cell per column. Mirrors
 * LogsheetLayout::referenceCells.
 */
export function referenceCells(
    parameters: LayoutParameter[],
    field: ReferenceRow['field'],
): ReferenceCell[] {
    const cells: ReferenceCell[] = [];
    let previousKey: string | null = null;

    for (const p of parameters) {
        const value = p[field] ?? '';
        const group = p.group_label || p.name;
        const key =
            [...value].length >= MERGE_REFERENCE_MIN_LENGTH
                ? JSON.stringify([group, value])
                : null;

        if (key !== null && key === previousKey) {
            cells[cells.length - 1].colspan++;
            continue;
        }

        cells.push({ value, colspan: 1 });
        previousKey = key;
    }

    return cells;
}

export type SummaryRow = { label: string; values: Record<string, string> };

/**
 * MIN / MAX / RATA2 of every number column over the filled hours (the Logsheet
 * Lokal 03 footer), from `grid` = time slot → `p_{id}` → value. Mirrors
 * LogsheetLayout::summaryRows.
 */
export function summaryRows(
    parameters: { id: number; input_type?: string }[],
    grid: Record<string, Record<string, string | number | null | undefined>>,
): SummaryRow[] {
    const format = (n: number) => String(Math.round(n * 100) / 100);
    const out: SummaryRow[] = [
        { label: 'MIN', values: {} },
        { label: 'MAX', values: {} },
        { label: 'RATA2', values: {} },
    ];

    for (const p of parameters) {
        if ((p.input_type ?? 'number') !== 'number') {
            continue;
        }

        const key = `p_${p.id}`;
        const numbers = Object.values(grid)
            .map((values) => values?.[key])
            .filter(
                (v) =>
                    v !== null &&
                    v !== undefined &&
                    v !== '' &&
                    Number.isFinite(Number(v)),
            )
            .map(Number);

        if (numbers.length === 0) {
            continue;
        }

        out[0].values[key] = format(Math.min(...numbers));
        out[1].values[key] = format(Math.max(...numbers));
        out[2].values[key] = format(
            numbers.reduce((a, b) => a + b, 0) / numbers.length,
        );
    }

    return out;
}
