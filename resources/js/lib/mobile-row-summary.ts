/**
 * Title & one-line summary of a table row for the phone compact list
 * (components/mobile/compact-rows.tsx), derived from the table's column
 * definitions so every form gets a readable collapsed line without extra code.
 */

type Column = { key: string; label: string; type?: string; group?: string };
type Value = string | number | null | undefined;

/** Columns that usually name the row (the item, activity, finding …). */
const TITLE_HINT =
    /nama|uraian|material|peralatan|kegiatan|item|deskripsi|temuan|lokasi|pekerjaan|jenis|barang|tools|keterangan pekerjaan|sample|mesin|apd|judul/i;

const SKIP_TYPES = new Set(['image', 'check']);

const filled = (value: Value) =>
    value !== null && value !== undefined && String(value).trim() !== '';

const plain = (value: Value) =>
    String(value ?? '')
        .replace(/\s+/g, ' ')
        .trim();

export function titleColumn<C extends Column>(columns: C[]): C | undefined {
    const textual = columns.filter(
        (c) =>
            !c.type ||
            c.type === 'text' ||
            c.type === 'textarea' ||
            c.type === 'readonly',
    );

    return (
        textual.find((c) => TITLE_HINT.test(c.label)) ??
        textual[0] ??
        columns[0]
    );
}

export function rowSummary<C extends Column>(
    columns: C[],
    data: Record<string, Value>,
    maxParts = 3,
): { title: string; subtitle: string; searchText: string; empty: boolean } {
    const main = titleColumn(columns);
    const title = main && filled(data[main.key]) ? plain(data[main.key]) : '';
    const parts: string[] = [];
    const derived: string[] = [];
    const ticked: string[] = [];

    for (const column of columns) {
        if (column === main) {
            continue;
        }

        const value = data[column.key];

        if (column.type === 'check') {
            if (String(value ?? '') === '1') {
                ticked.push(column.label);
            }

            continue;
        }

        if (
            SKIP_TYPES.has(column.type ?? '') ||
            !filled(value) ||
            parts.length >= maxParts
        ) {
            continue;
        }

        // Computed columns always hold a value (0 …): shown, but they don't make a row filled.
        (column.type === 'computed' ? derived : parts).push(
            `${column.label}: ${plain(value).slice(0, 40)}`,
        );
    }

    if (ticked.length > 0) {
        parts.unshift(ticked.join(', '));
    }

    const empty = !title && parts.length === 0 && ticked.length === 0;

    if (!empty) {
        parts.push(...derived);
    }

    return {
        title:
            title ||
            (empty
                ? 'Baris kosong — ketuk untuk mengisi'
                : (parts.shift() ?? '')),
        subtitle: parts.slice(0, maxParts).join(' · '),
        searchText: columns.map((c) => plain(data[c.key])).join(' '),
        empty,
    };
}
