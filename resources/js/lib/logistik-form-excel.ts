import ExcelJS from 'exceljs';
import { applyStyle, buildDocumentHeader, downloadWorkbook, mergeCells, SPECS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * The Logistik & Gudang table forms (App\Support\LogistikForms): shared
 * types, the live computed columns & section totals (mirroring
 * LogistikForm::compute / totals) and the Excel export mirroring
 * resources/views/logistik/input/form-pdf.blade.php.
 */

export type FormColumn = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'select' | 'date' | 'check' | 'image' | 'computed';
    /** `check` columns sharing this group allow one tick per row (e.g. Open / Close). */
    exclusive?: string;
    options?: string[];
    group?: string;
    width?: number;
    align?: 'l' | 'c' | 'r';
    default?: string | number;
    computed?: { formula?: string; count_filled?: string[][]; percent?: [string, string] };
};
export type FormSection = { key: string; title: string | null; blank_rows: number; totals: string[] };
export type FormDefinition = { key: string; title: string; kop: string; description: string; orientation: 'portrait' | 'landscape'; columns: FormColumn[]; sections: FormSection[]; notes: string[] };
export type FormValue = string | number | null;
export type FormRow = { section: string; data: Record<string, FormValue>; image_urls: Record<string, string> };
export type FormSummary = { title: string; columns: string[]; rows: (string | number)[][] } | null;

const num = (value: FormValue | undefined): number => {
    const n = typeof value === 'number' ? value : Number(value);

    return value === null || value === undefined || value === '' || Number.isNaN(n) ? 0 : n;
};

const tidy = (value: number): number => (Number.isInteger(value) ? value : Math.round(value * 100) / 100);

/** `a + b * 2 - c` over column keys and numbers (* and / before + and -). */
const evaluate = (formula: string, data: Record<string, FormValue>): number => {
    const tokens = formula.trim().split(/\s+/);
    const value = (token: string) => (/^-?\d+(\.\d+)?$/.test(token) ? Number(token) : num(data[token]));
    const terms = [value(tokens.shift() ?? '0')];
    const signs = [1];

    while (tokens.length > 0) {
        const operator = tokens.shift();
        const operand = value(tokens.shift() ?? '0');

        if (operator === '*') {
            terms[terms.length - 1] *= operand;
        } else if (operator === '/') {
            terms[terms.length - 1] = operand !== 0 ? terms[terms.length - 1] / operand : 0;
        } else {
            terms.push(operand);
            signs.push(operator === '-' ? -1 : 1);
        }
    }

    return terms.reduce((sum, term, i) => sum + term * signs[i], 0);
};

/** A cell as printed in the Excel export (photos noted, ticks as ✓, dates as dd/mm/yyyy). */
export const excelCellValue = (column: FormColumn, value: FormValue): FormValue => {
    if (column.type === 'image') {
        return value ? 'Foto terlampir (PDF)' : '';
    }

    if (column.type === 'check') {
        return String(value) === '1' ? '✓' : '';
    }

    if (column.type === 'date' && typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split('-');

        return `${day}/${month}/${year}`;
    }

    return value;
};

/** The row data with its computed columns filled in. */
export const computeRow = (columns: FormColumn[], data: Record<string, FormValue>): Record<string, FormValue> => {
    const result = { ...data };

    for (const column of columns) {
        const spec = column.computed;

        if (column.type !== 'computed' || !spec) {
            continue;
        }

        if (spec.formula !== undefined) {
            result[column.key] = tidy(evaluate(spec.formula, result));
        } else if (spec.count_filled) {
            result[column.key] = spec.count_filled.filter((keys) => keys.some((key) => num(result[key]) > 0)).length;
        } else if (spec.percent) {
            const [numerator, denominator] = spec.percent;
            result[column.key] = num(result[denominator]) > 0 ? `${Math.round((num(result[numerator]) / num(result[denominator])) * 100)}%` : '0%';
        }
    }

    return result;
};

export const sectionTotals = (section: FormSection, rows: Record<string, FormValue>[]): Record<string, number> =>
    Object.fromEntries(section.totals.map((key) => [key, tidy(rows.reduce((sum, row) => sum + num(row[key]), 0))]));

const CELL: StyleSpec = { valign: 'center', border: true, wrap: true };
const HEADER: StyleSpec = { ...SPECS.headerGrey, fill: '5BC8F5' };
const SECTION: StyleSpec = { ...CELL, fill: 'FFFF00', bold: true };
const ALIGN = { l: 'left', c: 'center', r: 'right' } as const;

const put = (sheet: ExcelJS.Worksheet, row: number, col: number, value: FormValue | undefined, spec?: StyleSpec) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';

    if (spec) {
        applyStyle(cell, spec);
    }
};

export async function downloadLogistikFormWorkbook(form: FormDefinition, unitName: string, periodLabel: string, rows: FormRow[], summary: FormSummary, filename: string) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet(form.title.slice(0, 31), {
        views: [{ showGridLines: false }],
        pageSetup: { orientation: form.orientation, paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });

    const columns = form.columns;
    const totalCols = columns.length + 1;
    const colWidths = [6, ...columns.map((column) => Math.max(8, Math.round((column.width ?? 90) / 7)))];
    colWidths.forEach((width, i) => (worksheet.getColumn(i + 1).width = width));

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT', `LAPORAN PROJECT SENTRAL ${unitName.toUpperCase()}`, form.kop],
        barTitle: `${form.title.toUpperCase()} — ${periodLabel.toUpperCase()}`,
    });

    put(worksheet, 6, 2, `UNIT : ${unitName.toUpperCase()}`, { bold: true, align: 'left' });
    put(worksheet, 7, 2, `PERIODE/BULAN : ${periodLabel.toUpperCase()}`, { bold: true, align: 'left' });

    // Header: groups on row 9, labels on row 10.
    const hasGroups = columns.some((column) => column.group);
    const top = 9;
    put(worksheet, top, 1, 'NO', HEADER);

    if (hasGroups) {
        put(worksheet, top + 1, 1, '', HEADER);
        mergeCells(worksheet, top - 1, 0, top, 0);
    }

    columns.forEach((column, i) => {
        const col = i + 2;

        if (column.group) {
            const first = i === 0 || columns[i - 1].group !== column.group;
            put(worksheet, top, col, first ? column.group : '', HEADER);
            put(worksheet, top + 1, col, column.label, HEADER);

            if (first) {
                const span = columns.slice(i).findIndex((c) => c.group !== column.group);
                const last = span === -1 ? columns.length - 1 : i + span - 1;

                if (last > i) {
                    mergeCells(worksheet, top - 1, col - 1, top - 1, last + 1);
                }
            }
        } else {
            put(worksheet, top, col, column.label, HEADER);

            if (hasGroups) {
                put(worksheet, top + 1, col, '', HEADER);
                mergeCells(worksheet, top - 1, col - 1, top, col - 1);
            }
        }
    });

    let r = hasGroups ? top + 2 : top + 1;

    for (const section of form.sections) {
        if (section.title) {
            put(worksheet, r, 1, section.title, SECTION);

            for (let c = 2; c <= totalCols; c++) {
                applyStyle(worksheet.getRow(r).getCell(c), SECTION);
            }

            mergeCells(worksheet, r - 1, 0, r - 1, totalCols - 1);
            r++;
        }

        const sectionRows = rows.filter((row) => row.section === section.key).map((row) => computeRow(columns, row.data));

        sectionRows.forEach((data, index) => {
            put(worksheet, r, 1, index + 1, { ...CELL, align: 'center' });
            columns.forEach((column, i) => {
                const value = excelCellValue(column, data[column.key]);
                put(worksheet, r, i + 2, value, { ...CELL, align: ALIGN[column.align ?? 'l'] });
            });
            r++;
        });

        if (section.totals.length > 0) {
            const totals = sectionTotals(section, sectionRows);
            put(worksheet, r, 1, '', CELL);
            columns.forEach((column, i) => put(worksheet, r, i + 2, i === 0 ? 'Total' : totals[column.key] ?? '', { ...CELL, bold: true, align: 'center' }));
            r++;
        }
    }

    if (summary) {
        r++;
        put(worksheet, r, 2, summary.title, { ...HEADER });
        r++;
        summary.columns.forEach((label, i) => put(worksheet, r, i + 2, label, HEADER));
        r++;
        summary.rows.forEach((row) => {
            row.forEach((cell, i) => put(worksheet, r, i + 2, cell, { ...CELL, align: 'center' }));
            r++;
        });
    }

    if (form.notes.length > 0) {
        r++;
        put(worksheet, r, 1, 'CATATAN :', { bold: true, align: 'left' });
        form.notes.forEach((note, i) => put(worksheet, r + i + 1, 2, `${i + 1}. ${note}`, { align: 'left' }));
    }

    await downloadWorkbook(workbook, filename);
}
