import ExcelJS from 'exceljs';
import {
    applyStyle,
    downloadWorkbook,
    loadImageBase64,
    mergeCells,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel export of one machine's Logsheet Operator for one day (time slot ×
 * parameter), mirroring resources/views/operator/logsheet-pdf.blade.php.
 */

export type LogsheetExcel = {
    unit: string;
    engine: string;
    hariTanggal: string;
    shift: string | null;
    status: string;
    parameters: { id: number; label: string; unit_of_measure: string | null }[];
    rows: {
        time_slot: string;
        values: Record<string, number | string | null>;
    }[];
    stats: { label: string; value: string; unit?: string }[];
};

const PEAK = [
    '17:30',
    '18:00',
    '18:30',
    '19:00',
    '19:30',
    '20:00',
    '20:30',
    '21:00',
    '21:30',
];
const CELL: StyleSpec = {
    align: 'center',
    valign: 'center',
    border: true,
    size: 9,
};
const HEAD: StyleSpec = {
    ...CELL,
    bold: true,
    wrap: true,
    fill: 'D9D9D9',
    size: 8,
};

const put = (
    sheet: ExcelJS.Worksheet,
    row: number,
    col: number,
    value: string | number | null,
    spec: StyleSpec,
) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';
    applyStyle(cell, spec);
};

/** Numbers stay numbers in Excel (so they can be summed / charted). */
const numeric = (
    value: number | string | null | undefined,
): string | number => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const n = Number(value);

    return Number.isFinite(n) ? n : String(value);
};

export async function downloadLogsheetWorkbook(
    data: LogsheetExcel,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Logsheet', {
        views: [
            { showGridLines: false, state: 'frozen', xSplit: 1, ySplit: 6 },
        ],
        pageSetup: {
            orientation: 'landscape',
            paperSize: 9,
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });

    const totalCols = data.parameters.length + 1;
    sheet.getColumn(1).width = 8;
    data.parameters.forEach((_, i) => (sheet.getColumn(i + 2).width = 11));

    // Title block.
    sheet.getRow(1).height = 34;
    const pln = await loadImageBase64('/logo/sidebar-logo.png');

    if (pln) {
        sheet.addImage(workbook.addImage({ base64: pln, extension: 'png' }), {
            tl: { col: 0.1, row: 0.1 },
            ext: { width: 140, height: 38 },
        });
    }

    const mkp = await loadImageBase64('/logo/mkp.jpg');

    if (mkp) {
        sheet.addImage(workbook.addImage({ base64: mkp, extension: 'jpeg' }), {
            tl: { col: totalCols - 0.8, row: 0.1 },
            ext: { width: 54, height: 40 },
        });
    }

    put(sheet, 2, 1, `LOGSHEET OPERATOR ${data.unit.toUpperCase()}`, {
        bold: true,
        size: 13,
        align: 'center',
    });
    mergeCells(sheet, 1, 0, 1, totalCols - 1);
    put(
        sheet,
        3,
        1,
        `${data.engine.toUpperCase()} — ${data.hariTanggal} · Regu/Shift: ${data.shift ?? '-'} · Status: ${data.status}`,
        { size: 9, align: 'center', italic: true },
    );
    mergeCells(sheet, 2, 0, 2, totalCols - 1);

    // Header rows: parameter label + unit.
    const top = 5;
    put(sheet, top, 1, 'JAM', HEAD);
    put(sheet, top + 1, 1, '', HEAD);
    mergeCells(sheet, top - 1, 0, top, 0);
    data.parameters.forEach((p, i) => {
        put(sheet, top, i + 2, p.label, HEAD);
        put(sheet, top + 1, i + 2, p.unit_of_measure || '-', {
            ...HEAD,
            bold: false,
            fill: 'F2F2F2',
        });
    });
    sheet.getRow(top).height = 32;

    let r = top + 2;

    for (const row of data.rows) {
        const fill = PEAK.includes(row.time_slot) ? 'FFF7E0' : undefined;
        put(sheet, r, 1, row.time_slot, {
            ...CELL,
            bold: true,
            fill: 'F2F2F2',
        });
        data.parameters.forEach((p, i) =>
            put(sheet, r, i + 2, numeric(row.values[`p_${p.id}`]), {
                ...CELL,
                fill,
            }),
        );
        r++;
    }

    r++;
    data.stats.forEach((stat, i) => {
        put(sheet, r + i, 1, stat.label, { size: 9, bold: true });
        mergeCells(sheet, r + i - 1, 0, r + i - 1, 2);
        put(
            sheet,
            r + i,
            4,
            `${stat.value}${stat.unit ? ` ${stat.unit}` : ''}`,
            { size: 9 },
        );
    });

    await downloadWorkbook(workbook, filename);
}
