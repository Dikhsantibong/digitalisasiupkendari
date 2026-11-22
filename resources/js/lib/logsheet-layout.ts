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
