import * as XLSX from 'xlsx';

/** A single document cell: text, optional bold, optional align (l/c/r). */
export type GridCell = { t: string; b?: boolean; a?: 'l' | 'c' | 'r' };

/** The canonical document grid stored on the server and rendered to PDF. */
export type DocumentGrid = {
    name?: string;
    cols: number;
    col_widths?: number[];
    merges?: [number, number, number, number][];
    rows: GridCell[][];
};

const ALIGN_TO_XS: Record<string, string> = { l: 'left', c: 'center', r: 'right' };
const XS_TO_ALIGN: Record<string, 'l' | 'c' | 'r'> = {
    left: 'l',
    center: 'c',
    right: 'r',
};

/** 0 -> A, 25 -> Z, 26 -> AA. */
export function columnLetter(index: number): string {
    let n = index;
    let letter = '';

    do {
        letter = String.fromCharCode(65 + (n % 26)) + letter;
        n = Math.floor(n / 26) - 1;
    } while (n >= 0);

    return letter;
}

const cellRef = (row: number, col: number) => `${columnLetter(col)}${row + 1}`;

/** Build the x-spreadsheet data object from our canonical grid. */
export function gridToSheet(grid: DocumentGrid): Record<string, unknown> {
    const styles: Record<string, unknown>[] = [];
    const styleIndex = new Map<string, number>();
    const styleFor = (cell: GridCell): number | undefined => {
        if (!cell.b && (!cell.a || cell.a === 'l')) {
            return undefined;
        }

        const key = `${cell.b ? 1 : 0}-${cell.a ?? 'l'}`;

        if (!styleIndex.has(key)) {
            const style: Record<string, unknown> = {};

            if (cell.b) {
                style.font = { bold: true };
            }

            if (cell.a && cell.a !== 'l') {
                style.align = ALIGN_TO_XS[cell.a];
            }

            styleIndex.set(key, styles.length);
            styles.push(style);
        }

        return styleIndex.get(key);
    };

    const mergeSpan = new Map<string, [number, number]>();
    const mergeRefs: string[] = [];

    for (const [r1, c1, r2, c2] of grid.merges ?? []) {
        mergeSpan.set(`${r1}:${c1}`, [r2 - r1, c2 - c1]);
        mergeRefs.push(`${cellRef(r1, c1)}:${cellRef(r2, c2)}`);
    }

    const rows: Record<string, unknown> = { len: Math.max(grid.rows.length, 30) };
    grid.rows.forEach((cells, r) => {
        const cellMap: Record<string, unknown> = {};
        cells.forEach((cell, c) => {
            const entry: Record<string, unknown> = { text: cell.t ?? '' };
            const style = styleFor(cell);

            if (style !== undefined) {
                entry.style = style;
            }

            const span = mergeSpan.get(`${r}:${c}`);

            if (span) {
                entry.merge = span;
            }

            cellMap[c] = entry;
        });
        rows[r] = { cells: cellMap };
    });

    const cols: Record<string, unknown> = { len: Math.max(grid.cols, 10) };
    (grid.col_widths ?? []).forEach((width, c) => {
        cols[c] = { width };
    });

    return {
        name: grid.name ?? 'Dokumen',
        styles,
        merges: mergeRefs,
        rows,
        cols,
    };
}

const parseRef = (ref: string): [number, number] => {
    const match = ref.match(/^([A-Z]+)(\d+)$/);

    if (!match) {
        return [0, 0];
    }

    let col = 0;

    for (const ch of match[1]) {
        col = col * 26 + (ch.charCodeAt(0) - 64);
    }

    return [Number(match[2]) - 1, col - 1];
};

/** Convert x-spreadsheet's getData() back to our canonical grid. */
export function sheetToGrid(data: Record<string, unknown>): DocumentGrid {
    const sheet = (Array.isArray(data) ? data[0] : data) as Record<string, unknown>;
    const styles = (sheet.styles as Record<string, unknown>[]) ?? [];
    const rowsData = (sheet.rows as Record<string, unknown>) ?? {};
    const colsData = (sheet.cols as Record<string, unknown>) ?? {};
    const rowLen = Number((rowsData.len as number) ?? 0);
    const colLen = Number((colsData.len as number) ?? 0);

    const merges: [number, number, number, number][] = [];

    for (const ref of (sheet.merges as string[]) ?? []) {
        const [a, b] = ref.split(':');
        const [r1, c1] = parseRef(a);
        const [r2, c2] = parseRef(b ?? a);
        merges.push([r1, c1, r2, c2]);
    }

    let maxCol = colLen;
    const rows: GridCell[][] = [];

    for (let r = 0; r < rowLen; r++) {
        const rowObj = rowsData[r] as Record<string, unknown> | undefined;
        const cellsObj = (rowObj?.cells as Record<string, unknown>) ?? {};
        const cells: GridCell[] = [];
        const colKeys = Object.keys(cellsObj).map(Number);
        const rowMaxCol = colKeys.length ? Math.max(...colKeys) + 1 : 0;
        maxCol = Math.max(maxCol, rowMaxCol);

        for (let c = 0; c < Math.max(colLen, rowMaxCol); c++) {
            const cell = cellsObj[c] as Record<string, unknown> | undefined;
            const style = cell?.style !== undefined ? styles[cell.style as number] : undefined;
            const font = style?.font as { bold?: boolean } | undefined;
            const align = style?.align as string | undefined;
            cells.push({
                t: String(cell?.text ?? ''),
                ...(font?.bold ? { b: true } : {}),
                ...(align && XS_TO_ALIGN[align] ? { a: XS_TO_ALIGN[align] } : {}),
            });
        }

        rows.push(cells);
    }

    const colWidths: number[] = [];

    for (let c = 0; c < maxCol; c++) {
        const col = colsData[c] as { width?: number } | undefined;

        if (col?.width) {
            colWidths[c] = col.width;
        }
    }

    return {
        name: String(sheet.name ?? 'Dokumen'),
        cols: Math.max(maxCol, 1),
        col_widths: colWidths.length ? colWidths : undefined,
        merges,
        rows,
    };
}

/** Download the grid as an .xlsx file (client-side, via SheetJS). */
export function downloadGridAsXlsx(grid: DocumentGrid, filename: string): void {
    const aoa = grid.rows.map((row) => row.map((cell) => cell.t ?? ''));
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!merges'] = (grid.merges ?? []).map(([r1, c1, r2, c2]) => ({
        s: { r: r1, c: c1 },
        e: { r: r2, c: c2 },
    }));

    if (grid.col_widths?.length) {
        ws['!cols'] = grid.col_widths.map((width) => ({ wpx: width }));
    }

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, (grid.name ?? 'Dokumen').slice(0, 31));
    XLSX.writeFile(wb, filename);
}
